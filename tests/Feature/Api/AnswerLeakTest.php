<?php

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Str;

/**
 * Before the player answers, no endpoint may contain the correct answer, place, year or reveal.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url').'/');
});

function assertNoAnswerLeak(array $payload, Question $question): void
{
    $forbiddenKeys = ['latitude', 'longitude', 'answer_year', 'year', 'location_name', 'reveal_text', 'correct'];

    array_walk_recursive($payload, function ($value, $key) use ($forbiddenKeys) {
        expect($forbiddenKeys)->not->toContain($key);
    });

    $walk = function (array $node) use (&$walk, $forbiddenKeys): void {
        foreach ($node as $key => $value) {
            expect($forbiddenKeys)->not->toContain($key);
            if (is_array($value)) {
                $walk($value);
            }
        }
    };
    $walk($payload);

    $raw = json_encode($payload, JSON_UNESCAPED_UNICODE);
    expect($raw)->not->toContain((string) $question->latitude)
        ->and($raw)->not->toContain((string) $question->longitude)
        ->and($raw)->not->toContain($question->answer)
        ->and($raw)->not->toContain($question->location_name)
        ->and($raw)->not->toContain($question->reveal_text);
}

it('does not leak the answer when a game starts or is reloaded', function () {
    $quiz = Quiz::factory()->published()->withQuestions(3)->create(['round_size' => 3]);

    $start = $this->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])->assertCreated()->json();
    $question = Question::where('public_id', $start['question']['id'])->sole();
    $reload = $this->getJson("/api/v1/game-sessions/{$start['id']}")->assertOk()->json();

    assertNoAnswerLeak($start, $question);
    assertNoAnswerLeak($reload, $question);
    expect($start['answer'])->toBeNull();
});

it('does not leak the next answer after advancing', function () {
    $quiz = Quiz::factory()->published()->withQuestions(2)->create(['round_size' => 2]);
    $start = $this->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])->json();
    $this->postJson("/api/v1/game-sessions/{$start['id']}/answers", [
        'question_id' => $start['question']['id'], 'latitude' => 0, 'longitude' => 0, 'year' => 1900,
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    $next = $this->postJson("/api/v1/game-sessions/{$start['id']}/advance", ['position' => 1])->assertOk()->json();

    assertNoAnswerLeak($next, Question::where('public_id', $next['question']['id'])->sole());
});

it('does not leak answers through the quiz list', function () {
    $quiz = Quiz::factory()->published()->withQuestions(1)->create();

    assertNoAnswerLeak($this->getJson('/api/v1/quizzes')->json(), $quiz->questions->first());
});

it('does not leak the answer through the round timer, even past the deadline within grace', function () {
    config(['game.round.time_limit' => 90, 'game.round.grace' => 3]);
    $this->freezeSecond();
    $quiz = Quiz::factory()->published()->withQuestions(1)->create(['round_size' => 1]);
    $start = $this->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])->assertCreated()->json();
    $question = Question::where('public_id', $start['question']['id'])->sole();

    $this->travel(92)->seconds();
    $late = $this->getJson("/api/v1/game-sessions/{$start['id']}")->assertOk()->json();

    expect(array_keys($late['round']))->toBe(['time_limit_ms', 'grace_ms', 'started_at', 'deadline_at', 'remaining_ms'])
        ->and($late['round']['remaining_ms'])->toBe(0)
        ->and($late['answer'])->toBeNull();
    assertNoAnswerLeak($late, $question);
});

it('does not reveal a skipped question nor the next one after the player comes back', function () {
    config(['game.round.time_limit' => 90, 'game.round.grace' => 3]);
    $this->freezeSecond();
    $quiz = Quiz::factory()->published()->withQuestions(2)->create(['round_size' => 2]);
    $start = $this->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])->assertCreated()->json();
    $skipped = Question::where('public_id', $start['question']['id'])->sole();

    $this->travel(5)->minutes();
    $back = $this->getJson("/api/v1/game-sessions/{$start['id']}")->assertOk()->json();

    expect($back['notice'])->toBe('previous_round_expired')->and($back['answer'])->toBeNull();
    assertNoAnswerLeak($back, $skipped);
    assertNoAnswerLeak($back, Question::where('public_id', $back['question']['id'])->sole());
});
