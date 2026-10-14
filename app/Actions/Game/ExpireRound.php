<?php

namespace App\Actions\Game;

use App\Models\Answer;
use App\Models\GameSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ExpireRound
{
    public function __construct(private readonly MoveToNextRound $moveNext) {}

    /**
     * Skips the current round if it ran out of time unanswered (the player was away or offline): stores a
     * zero-point timed-out answer without a guess and moves straight to the next question with a fresh
     * timer, or completes the game. Safe to call repeatedly and concurrently: the session lock and the
     * unique (game, question) answer index allow at most one answer per round.
     *
     * @return bool whether a round was skipped
     */
    public function handle(GameSession $session): bool
    {
        return DB::transaction(function () use ($session): bool {
            $locked = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            return $this->expireLocked($locked);
        });
    }

    /**
     * Same as handle() for a caller that already holds the session row lock in its transaction.
     */
    public function expireLocked(GameSession $locked): bool
    {
        if (! $locked->isInProgress()) {
            return false;
        }

        $round = $locked->currentRound();

        if ($round === null || ! $round->isExpired(now()->toImmutable())) {
            return false;
        }

        if (Answer::query()->where('game_session_id', $locked->id)->where('question_id', $round->questionId)->exists()) {
            return false;
        }

        // The score totals stay unchanged: a skipped round is worth zero.
        Answer::create([
            'game_session_id' => $locked->id,
            'question_id' => $round->questionId,
            'idempotency_key' => (string) Str::uuid(),
            'timed_out' => true,
            'geo_score' => 0,
            'year_score' => 0,
            'total_score' => 0,
            'duration_ms' => $locked->round_time_limit_seconds * 1000,
            'answered_at' => $round->deadlineAt,
        ]);

        $this->moveNext->handle($locked);

        return true;
    }
}
