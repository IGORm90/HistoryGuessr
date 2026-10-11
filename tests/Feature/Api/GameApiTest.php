<?php

use App\Enums\GameSessionStatus;
use App\Models\Answer;
use App\Models\GameSession;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    // Sanctum only starts the cookie session for requests from the SPA's own origin.
    $this->withHeader('Referer', config('app.url').'/');
});

function startGame(Quiz $quiz): array
{
    return test()->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])->assertCreated()->json();
}

function answerCurrent(string $gameId, array $overrides = []): TestResponse
{
    $state = test()->getJson("/api/v1/game-sessions/{$gameId}")->json();

    return test()->postJson("/api/v1/game-sessions/{$gameId}/answers", array_merge([
        'question_id' => $state['question']['id'],
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'year' => 1900,
        'idempotency_key' => (string) Str::uuid(),
        'duration_ms' => 12000,
    ], $overrides));
}

function advance(string $gameId, int $position): TestResponse
{
    return test()->postJson("/api/v1/game-sessions/{$gameId}/advance", ['position' => $position]);
}

describe('starting a game', function () {
    it('creates a session with round_size questions in a fixed order', function () {
        $quiz = Quiz::factory()->published()->withQuestions(8)->create(['round_size' => 5]);

        $game = startGame($quiz);

        $session = GameSession::where('public_id', $game['id'])->sole();
        $positions = DB::table('game_session_questions')->where('game_session_id', $session->id)->orderBy('position')->get();

        expect($game)->toMatchArray(['status' => 'in_progress', 'position' => 1, 'question_count' => 5, 'total_score' => 0])
            ->and($positions->pluck('position')->all())->toBe([1, 2, 3, 4, 5])
            ->and($positions->pluck('question_id')->unique())->toHaveCount(5)
            ->and($game['question']['id'])->toBe(Question::find($positions[0]->question_id)->public_id);

        // The order stays fixed on later reads.
        $this->getJson("/api/v1/game-sessions/{$game['id']}")->assertJsonPath('question.id', $game['question']['id']);
    });

    it('refuses unpublished quizzes', function () {
        $quiz = Quiz::factory()->withQuestions(5)->create();

        $this->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors('quiz_id');
    });

    it('never puts draft questions into a new game', function () {
        $quiz = Quiz::factory()->published()->withQuestions(3)->create(['round_size' => 5]);
        $draft = Question::factory()->create();
        $quiz->questions()->attach($draft, ['position' => 4]);

        $game = startGame($quiz);

        expect($game['question_count'])->toBe(3)
            ->and(DB::table('game_session_questions')->where('question_id', $draft->id)->exists())->toBeFalse();
    });

    it('reports a quiz without published questions as unavailable', function () {
        $quiz = Quiz::factory()->published()->create();
        $quiz->questions()->attach(Question::factory()->create(), ['position' => 1]);

        $this->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])
            ->assertConflict()
            ->assertJsonPath('code', 'quiz_unavailable');
    });

    it('requires a browser session', function () {
        $quiz = Quiz::factory()->published()->withQuestions(1)->create();

        $this->withoutHeader('Referer')
            ->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])
            ->assertStatus(400)
            ->assertJsonPath('code', 'session_required');
    });
});

describe('answering', function () {
    beforeEach(function () {
        $this->quiz = Quiz::factory()->published()->withQuestions(3)->create(['round_size' => 3]);
        $this->game = startGame($this->quiz);
    });

    it('scores the guess on the server and reveals the answer', function () {
        $question = Question::where('public_id', $this->game['question']['id'])->sole();

        answerCurrent($this->game['id'], [
            'latitude' => $question->latitude,
            'longitude' => $question->longitude,
            'year' => $question->answer_year,
            'total_score' => 1, 'distance' => 999, 'geo_score' => 0, // must be ignored
        ])->assertSuccessful()
            ->assertJsonPath('score.total', 10000)
            ->assertJsonPath('distance_meters', 0)
            ->assertJsonPath('correct.latitude', $question->latitude)
            ->assertJsonPath('correct.year', $question->answer_year)
            ->assertJsonPath('correct.answer', $question->answer)
            ->assertJsonPath('correct.location_name', $question->location_name)
            ->assertJsonPath('reveal_text', $question->reveal_text)
            ->assertJsonPath('attribution.license', $question->image_license);

        expect(Answer::sole()->total_score)->toBe(10000)
            ->and(GameSession::sole()->total_score)->toBe(10000);
    });

    it('stores exactly one answer when the same request is retried', function () {
        $key = (string) Str::uuid();

        $first = answerCurrent($this->game['id'], ['idempotency_key' => $key])->assertCreated()->json();
        $retry = answerCurrent($this->game['id'], ['idempotency_key' => $key, 'latitude' => -10, 'year' => 1500])->assertOk()->json();

        expect($retry)->toBe($first)
            ->and(Answer::count())->toBe(1)
            ->and(GameSession::sole()->total_score)->toBe($first['score']['total']);
    });

    it('rejects a second answer with a different key', function () {
        answerCurrent($this->game['id'])->assertSuccessful();

        answerCurrent($this->game['id'])->assertConflict()->assertJsonPath('code', 'already_answered');
        expect(Answer::count())->toBe(1);
    });

    it('rejects answers to a question that is not current', function () {
        $session = GameSession::sole();
        $other = DB::table('game_session_questions')->where('game_session_id', $session->id)->where('position', 2)->value('question_id');

        answerCurrent($this->game['id'], ['question_id' => Question::find($other)->public_id])
            ->assertConflict()
            ->assertJsonPath('code', 'question_mismatch');
    });

    it('validates the guess', function () {
        answerCurrent($this->game['id'], ['latitude' => 91, 'longitude' => 181, 'year' => 0, 'idempotency_key' => 'nope'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude', 'year', 'idempotency_key']);
    });

    it('accepts BC years', function () {
        answerCurrent($this->game['id'], ['year' => -44])->assertSuccessful()->assertJsonPath('guess.year', -44);
    });

    it('does not let another player answer', function () {
        $this->flushSession();

        $this->postJson("/api/v1/game-sessions/{$this->game['id']}/answers", [
            'question_id' => $this->game['question']['id'], 'latitude' => 1, 'longitude' => 1, 'year' => 1900,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();
        $this->getJson("/api/v1/game-sessions/{$this->game['id']}")->assertNotFound()->assertJsonPath('code', 'not_found');
        expect(Answer::count())->toBe(0);
    });

    it('treats malformed game ids as not found', function () {
        $this->getJson('/api/v1/game-sessions/123')->assertNotFound();
    });

    it('stays within the query budget', function () {
        $state = $this->getJson("/api/v1/game-sessions/{$this->game['id']}")->json();
        DB::enableQueryLog();

        $this->postJson("/api/v1/game-sessions/{$this->game['id']}/answers", [
            'question_id' => $state['question']['id'],
            'latitude' => 1, 'longitude' => 1, 'year' => 1900,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated();

        expect(count(DB::getQueryLog()))->toBeLessThanOrEqual(10);
    });
});

describe('advancing', function () {
    beforeEach(function () {
        $this->quiz = Quiz::factory()->published()->withQuestions(2)->create(['round_size' => 2]);
        $this->game = startGame($this->quiz);
    });

    it('requires an answer first', function () {
        advance($this->game['id'], 1)->assertConflict()->assertJsonPath('code', 'answer_required');
    });

    it('moves to the next question without revealing it', function () {
        answerCurrent($this->game['id'])->assertSuccessful();

        advance($this->game['id'], 1)
            ->assertOk()
            ->assertJsonPath('position', 2)
            ->assertJsonPath('answer', null);
    });

    it('ignores a repeated advance for the same position', function () {
        answerCurrent($this->game['id'])->assertSuccessful();
        advance($this->game['id'], 1)->assertOk();

        advance($this->game['id'], 1)->assertOk()->assertJsonPath('position', 2);
    });

    it('completes the game after the last question and serves the result', function () {
        $total = 0;
        foreach ([1, 2] as $position) {
            $total += answerCurrent($this->game['id'])->assertSuccessful()->json('score.total');
            $last = advance($this->game['id'], $position)->assertOk();
        }

        $last->assertJsonPath('status', 'completed')->assertJsonPath('question', null);
        expect(GameSession::sole()->status)->toBe(GameSessionStatus::Completed);

        $result = $this->getJson("/api/v1/game-sessions/{$this->game['id']}/result")->assertOk()->json();
        expect($result['total_score'])->toBe($total)
            ->and(array_sum(array_column(array_column($result['rounds'], 'score'), 'total')))->toBe($total)
            ->and(array_column($result['rounds'], 'position'))->toBe([1, 2])
            ->and($result['rounds'][0]['question']['answer'])
            ->toBe(Question::where('public_id', $result['rounds'][0]['question']['id'])->value('answer'))
            ->and($result['max_score'])->toBe(20000);
    });

    it('rejects answers after completion', function () {
        foreach ([1, 2] as $position) {
            answerCurrent($this->game['id'])->assertSuccessful();
            advance($this->game['id'], $position);
        }

        $this->postJson("/api/v1/game-sessions/{$this->game['id']}/answers", [
            'question_id' => (string) Str::uuid(), 'latitude' => 1, 'longitude' => 1, 'year' => 1900,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertConflict()->assertJsonPath('code', 'game_not_in_progress');
    });

    it('withholds the result until the game is completed', function () {
        $this->getJson("/api/v1/game-sessions/{$this->game['id']}/result")
            ->assertConflict()
            ->assertJsonPath('code', 'game_not_completed');
    });
});

it('restores the revealed answer after a reload', function () {
    $quiz = Quiz::factory()->published()->withQuestions(2)->create(['round_size' => 2]);
    $game = startGame($quiz);
    $answer = answerCurrent($game['id'])->json();

    $this->getJson("/api/v1/game-sessions/{$game['id']}")
        ->assertOk()
        ->assertJsonPath('position', 1)
        ->assertJsonPath('answer', $answer);
});

it('rate limits game mutations per browser session and per IP', function () {
    // The array session driver used in tests regenerates the id on every request,
    // so the limiter definition is checked directly; it was also verified live with curl.
    $request = Request::create('/api/v1/game-sessions', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7']);
    $request->setLaravelSession($session = app('session')->driver());

    $limits = RateLimiter::limiter('game')($request);

    expect(array_map(fn ($limit) => [$limit->maxAttempts, $limit->decaySeconds], $limits))->toBe([[60, 60], [300, 60]])
        ->and($limits[0]->key)->toBe('session:'.$session->getId())
        ->and($limits[1]->key)->toBe('ip:203.0.113.7');
});

it('applies the game limiter to mutating routes only', function () {
    $routes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn ($route) => str_starts_with($route->uri(), 'api/v1/game-sessions'))
        ->mapWithKeys(fn ($route) => [implode('|', $route->methods()).' '.$route->uri() => in_array('throttle:game', $route->gatherMiddleware(), true)]);

    expect($routes->filter()->keys()->sort()->values()->all())->toBe([
        'POST api/v1/game-sessions',
        'POST api/v1/game-sessions/{gameSession}/advance',
        'POST api/v1/game-sessions/{gameSession}/answers',
    ]);
});
