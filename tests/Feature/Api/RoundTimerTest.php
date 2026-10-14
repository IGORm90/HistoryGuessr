<?php

use App\Models\Answer;
use App\Models\GameSession;
use App\Models\Quiz;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Server-side round timer: 90 s per question from the moment the server shows it, 3 s grace.
 * At zero the client sends what was picked (`timed_out`); a round left unanswered past the grace
 * (player away) is skipped with zero points and the next question opens.
 */
beforeEach(function () {
    $this->withHeader('Referer', config('app.url').'/');
    config(['game.round.time_limit' => 90, 'game.round.grace' => 3]);
    $this->freezeSecond();

    $this->quiz = Quiz::factory()->published()->withQuestions(2)->create(['round_size' => 2]);
    $this->game = startGame($this->quiz);
});

/**
 * Answers without a preceding GET, which would itself settle an expired round.
 */
function submitGuess(array $game, array $overrides = []): TestResponse
{
    return test()->postJson("/api/v1/game-sessions/{$game['id']}/answers", array_merge([
        'question_id' => $game['question']['id'],
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'year' => 1900,
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides));
}

function roundRow(int $position): object
{
    return DB::table('game_session_questions')
        ->where('game_session_id', GameSession::sole()->id)
        ->where('position', $position)
        ->first();
}

it('starts the first round when the game starts', function () {
    $row = roundRow(1);

    expect($this->game['round'])->toMatchArray(['time_limit_ms' => 90_000, 'grace_ms' => 3_000, 'remaining_ms' => 90_000])
        ->and($row->started_at)->not->toBeNull()
        ->and(strtotime($row->deadline_at) - strtotime($row->started_at))->toBe(90)
        ->and(roundRow(2)->started_at)->toBeNull()
        ->and(GameSession::sole()->round_time_limit_seconds)->toBe(90);
});

it('keeps counting down across reloads', function () {
    $this->travel(30)->seconds();

    $this->getJson("/api/v1/game-sessions/{$this->game['id']}")
        ->assertOk()
        ->assertJsonPath('question.id', $this->game['question']['id'])
        ->assertJsonPath('answer', null)
        ->assertJsonPath('round.remaining_ms', 60_000);
});

it('snapshots the limit at game start', function () {
    config(['game.round.time_limit' => 10]);
    $this->travel(30)->seconds();

    $this->getJson("/api/v1/game-sessions/{$this->game['id']}")
        ->assertJsonPath('answer', null)
        ->assertJsonPath('round.time_limit_ms', 90_000);
});

it('accepts answers within the limit and the grace period and measures the time itself', function () {
    $this->travel(92)->seconds();

    submitGuess($this->game, ['duration_ms' => 1])
        ->assertCreated()
        ->assertJsonPath('timed_out', false)
        ->assertJsonPath('duration_ms', 92_000);

    $this->getJson("/api/v1/game-sessions/{$this->game['id']}")->assertJsonPath('round.remaining_ms', null);
});

it('refuses answers after the deadline plus grace', function () {
    $this->travel(94)->seconds();

    submitGuess($this->game)->assertConflict()->assertJsonPath('code', 'round_expired');

    expect(Answer::where('timed_out', false)->count())->toBe(0)
        ->and(GameSession::sole()->total_score)->toBe(0);
});

it('replays an answer accepted in time even after the deadline', function () {
    $key = (string) Str::uuid();
    $first = submitGuess($this->game, ['idempotency_key' => $key])->assertCreated()->json();
    $this->travel(10)->minutes();

    $replay = submitGuess($this->game, ['idempotency_key' => $key])->assertOk()->json();

    expect($replay)->toBe($first)->and(Answer::count())->toBe(1);
});

describe('timeout sent by the client', function () {
    it('scores the point and year picked when time ran out', function () {
        $this->travel(90)->seconds();

        $answer = submitGuess($this->game, ['timed_out' => true])
            ->assertCreated()
            ->assertJsonPath('timed_out', true)
            ->assertJsonPath('guess.year', 1900)
            ->assertJsonPath('duration_ms', 90_000)
            ->json();

        expect($answer['score']['total'])->toBeGreaterThan(0)
            ->and($answer['distance_meters'])->not->toBeNull()
            ->and(GameSession::sole()->total_score)->toBe($answer['score']['total']);
    });

    it('scores zero without a picked point', function () {
        $this->travel(90)->seconds();

        submitGuess($this->game, ['timed_out' => true, 'latitude' => null, 'longitude' => null, 'year' => null])
            ->assertCreated()
            ->assertJsonPath('timed_out', true)
            ->assertJsonPath('guess', null)
            ->assertJsonPath('distance_meters', null)
            ->assertJsonPath('score', ['geo' => 0, 'year' => 0, 'total' => 0]);

        expect(Answer::sole()->guessed_year)->toBeNull()->and(GameSession::sole()->total_score)->toBe(0);
    });

    it('ignores a year without a point', function () {
        $this->travel(90)->seconds();

        submitGuess($this->game, ['timed_out' => true, 'latitude' => null, 'longitude' => null])
            ->assertCreated()
            ->assertJsonPath('guess', null);
    });

    it('still requires a point and a year for a regular answer', function () {
        submitGuess($this->game, ['latitude' => null, 'longitude' => null, 'year' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude', 'year']);
        submitGuess($this->game, ['timed_out' => true, 'longitude' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    });

    it('accepts a timeout whenever the client reports it until the end of grace', function (int $seconds) {
        $this->travel($seconds)->seconds();

        submitGuess($this->game, ['timed_out' => true])->assertCreated()->assertJsonPath('timed_out', true);
    })->with([10, 88, 93]);

    it('refuses a timeout after the grace period', function () {
        $this->travel(94)->seconds();

        submitGuess($this->game, ['timed_out' => true])->assertConflict()->assertJsonPath('code', 'round_expired');
    });

    it('replays a timeout with the same key', function () {
        $key = (string) Str::uuid();
        $this->travel(90)->seconds();
        $first = submitGuess($this->game, ['timed_out' => true, 'idempotency_key' => $key])->assertCreated()->json();
        $this->travel(1)->minutes();

        $replay = submitGuess($this->game, ['timed_out' => true, 'idempotency_key' => $key])->assertOk()->json();

        expect($replay)->toBe($first)->and(Answer::count())->toBe(1);
    });
});

describe('player away when time ran out', function () {
    it('skips the round on read and opens the next question with a fresh timer', function () {
        $this->travel(1)->hours();

        $state = $this->getJson("/api/v1/game-sessions/{$this->game['id']}")->assertOk()->json();

        $answer = Answer::sole();
        expect($state)->toMatchArray(['position' => 2, 'answer' => null, 'notice' => 'previous_round_expired', 'total_score' => 0])
            ->and($state['question']['id'])->not->toBe($this->game['question']['id'])
            ->and($state['round']['remaining_ms'])->toBe(90_000)
            ->and($answer->timed_out)->toBeTrue()
            ->and($answer->guessed_latitude)->toBeNull()
            ->and($answer->total_score)->toBe(0)
            ->and($answer->answered_at->equalTo(CarbonImmutable::parse(roundRow(1)->deadline_at)))->toBeTrue()
            ->and(CarbonImmutable::parse(roundRow(2)->started_at)->equalTo(now()))->toBeTrue();
    });

    it('reports the skip only once', function () {
        $this->travel(5)->minutes();

        $this->getJson("/api/v1/game-sessions/{$this->game['id']}")->assertJsonPath('notice', 'previous_round_expired');
        $this->getJson("/api/v1/game-sessions/{$this->game['id']}")
            ->assertJsonPath('notice', null)
            ->assertJsonPath('position', 2);

        expect(Answer::count())->toBe(1);
    });

    it('completes the game when the last round is skipped', function () {
        submitGuess($this->game)->assertCreated();
        $next = advance($this->game['id'], 1)->assertOk()->json();
        $this->travel(2)->minutes();

        $this->getJson("/api/v1/game-sessions/{$next['id']}")
            ->assertOk()
            ->assertJsonPath('status', 'completed')
            ->assertJsonPath('question', null)
            ->assertJsonPath('notice', 'previous_round_expired');

        $this->getJson("/api/v1/game-sessions/{$next['id']}/result")->assertOk()->assertJsonPath('rounds.1.timed_out', true);
    });

    it('leaves the round alone within the grace period', function () {
        $this->travel(92)->seconds();

        $this->getJson("/api/v1/game-sessions/{$this->game['id']}")
            ->assertJsonPath('position', 1)
            ->assertJsonPath('answer', null)
            ->assertJsonPath('notice', null)
            ->assertJsonPath('round.remaining_ms', 0);

        expect(Answer::count())->toBe(0);
    });

    it('ignores an advance from the skipped position', function () {
        $this->travel(2)->minutes();
        $this->getJson("/api/v1/game-sessions/{$this->game['id']}")->assertJsonPath('position', 2);

        advance($this->game['id'], 1)->assertOk()->assertJsonPath('position', 2)->assertJsonPath('answer', null);
        expect(Answer::count())->toBe(1);
    });

    it('skips the round on advance without a read in between', function () {
        $this->travel(2)->minutes();

        advance($this->game['id'], 1)
            ->assertOk()
            ->assertJsonPath('position', 2)
            ->assertJsonPath('round.remaining_ms', 90_000);

        expect(Answer::sole()->timed_out)->toBeTrue();
    });
});

it('still requires an answer to advance before the round expires', function () {
    $this->travel(60)->seconds();

    advance($this->game['id'], 1)->assertConflict()->assertJsonPath('code', 'answer_required');
    expect(Answer::count())->toBe(0);
});

it('does not limit the reveal screen', function () {
    submitGuess($this->game)->assertCreated();
    $this->travel(10)->minutes();

    $next = advance($this->game['id'], 1)->assertOk()->json();
    expect($next['round']['remaining_ms'])->toBe(90_000);

    $this->travel(89)->seconds();
    submitGuess($next)->assertCreated();
});

it('marks both kinds of timeout in the result', function () {
    $this->travel(90)->seconds();
    $points = submitGuess($this->game, ['timed_out' => true])->assertCreated()->json('score.total');
    advance($this->game['id'], 1)->assertOk();
    $this->travel(2)->minutes();
    $this->getJson("/api/v1/game-sessions/{$this->game['id']}")->assertJsonPath('status', 'completed');

    $result = $this->getJson("/api/v1/game-sessions/{$this->game['id']}/result")->assertOk()->json();

    expect($result['rounds'][0])->toMatchArray(['timed_out' => true, 'guess' => ['latitude' => 48.8566, 'longitude' => 2.3522, 'year' => 1900]])
        ->and($result['rounds'][0]['score']['total'])->toBe($points)
        ->and($result['rounds'][1])->toMatchArray(['timed_out' => true, 'guess' => null, 'distance_meters' => null, 'score' => ['geo' => 0, 'year' => 0, 'total' => 0]])
        ->and($result['total_score'])->toBe($points);
});

it('never expires rounds started before the timer existed', function () {
    DB::table('game_session_questions')->update(['started_at' => null, 'deadline_at' => null]);
    $this->travel(1)->days();

    $this->getJson("/api/v1/game-sessions/{$this->game['id']}")
        ->assertJsonPath('position', 1)
        ->assertJsonPath('answer', null)
        ->assertJsonPath('round.remaining_ms', null);
    submitGuess($this->game)->assertCreated()->assertJsonPath('duration_ms', null);
});
