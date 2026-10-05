<?php

use App\Enums\PublicationStatus;
use App\Models\Answer;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('assigns a UUID public id used as the route key', function () {
    $question = Question::factory()->create();

    expect(Str::isUuid($question->public_id))->toBeTrue()
        ->and($question->getRouteKey())->toBe($question->public_id);
});

it('scopes published questions and quizzes', function () {
    Question::factory()->published()->create();
    Question::factory()->create();
    Question::factory()->archived()->create();
    Quiz::factory()->published()->create();
    Quiz::factory()->create();

    expect(Question::published()->count())->toBe(1)
        ->and(Quiz::published()->count())->toBe(1);
});

it('allows incomplete drafts but refuses to publish them', function () {
    $draft = Question::factory()->incomplete()->create();
    expect($draft->exists)->toBeTrue();

    $draft->update(['status' => PublicationStatus::Published, 'published_at' => now()]);
})->throws(QueryException::class, 'questions_published_complete');

it('refuses to publish a question without a license', function () {
    Question::factory()->published()->create(['image_license' => null]);
})->throws(QueryException::class, 'questions_published_complete');

it('rejects year zero at the database level', function () {
    Question::factory()->create(['answer_year' => 0]);
})->throws(QueryException::class, 'questions_answer_year_not_zero');

it('rejects coordinates out of range', function () {
    Question::factory()->create(['latitude' => 91]);
})->throws(QueryException::class, 'questions_latitude_range');

it('stores BC years', function () {
    $question = Question::factory()->bce()->create();

    expect($question->fresh()->answer_year)->toBeLessThan(0)
        ->and($question->historicalYear()?->isBeforeCommonEra())->toBeTrue();
});

it('keeps quiz questions in editorial order', function () {
    $quiz = Quiz::factory()->create();
    [$first, $second, $third] = Question::factory()->count(3)->create()->all();

    $quiz->questions()->attach([
        $third->id => ['position' => 3],
        $first->id => ['position' => 1],
        $second->id => ['position' => 2],
    ]);

    expect($quiz->questions()->pluck('questions.id')->all())->toBe([$first->id, $second->id, $third->id]);
});

it('does not attach the same question to a quiz twice', function () {
    $quiz = Quiz::factory()->create();
    $question = Question::factory()->create();

    $quiz->questions()->attach($question, ['position' => 1]);
    $quiz->questions()->attach($question, ['position' => 2]);
})->throws(UniqueConstraintViolationException::class);

it('creates quizzes with published questions via the factory', function () {
    $quiz = Quiz::factory()->published()->withQuestions(6)->create();

    expect($quiz->questions)->toHaveCount(6)
        ->and($quiz->questions->every(fn (Question $q) => $q->isPublished()))->toBeTrue()
        ->and($quiz->questions->pluck('pivot.position')->all())->toBe([1, 2, 3, 4, 5, 6]);
});

it('fixes a unique question order per game session', function () {
    $session = GameSession::factory()->create();
    $question = Question::factory()->published()->create();

    DB::table('game_session_questions')->insert(['game_session_id' => $session->id, 'question_id' => $question->id, 'position' => 1]);
    DB::table('game_session_questions')->insert(['game_session_id' => $session->id, 'question_id' => $question->id, 'position' => 2]);
})->throws(UniqueConstraintViolationException::class);

it('keeps the current position within the round', function () {
    GameSession::factory()->create(['question_count' => 5, 'current_position' => 6]);
})->throws(QueryException::class, 'game_sessions_position_range');

it('allows only one answer per question in a session', function () {
    $answer = Answer::factory()->create();

    Answer::factory()->create([
        'game_session_id' => $answer->game_session_id,
        'question_id' => $answer->question_id,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('enforces a unique idempotency key per session', function () {
    $answer = Answer::factory()->create();

    Answer::factory()->create([
        'game_session_id' => $answer->game_session_id,
        'idempotency_key' => $answer->idempotency_key,
    ]);
})->throws(UniqueConstraintViolationException::class);

it('requires the stored total to equal the score parts', function () {
    Answer::factory()->create(['geo_score' => 100, 'year_score' => 200, 'total_score' => 999]);
})->throws(QueryException::class, 'answers_total_is_sum');

it('prevents deleting a question that has answers', function () {
    $answer = Answer::factory()->create();

    $answer->question->delete();
})->throws(QueryException::class);
