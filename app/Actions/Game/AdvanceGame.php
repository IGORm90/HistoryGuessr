<?php

namespace App\Actions\Game;

use App\Exceptions\GameException;
use App\Models\Answer;
use App\Models\GameSession;
use Illuminate\Support\Facades\DB;

class AdvanceGame
{
    public function __construct(
        private readonly MoveToNextRound $moveNext,
        private readonly ExpireRound $expire,
    ) {}

    /**
     * Moves past the answered (or timed-out) question at `$fromPosition`; the last one completes the game.
     * The next question's round timer starts here. Idempotent: repeating the call for a position
     * already left (double click, retry) changes nothing.
     */
    public function handle(GameSession $session, int $fromPosition): GameSession
    {
        return DB::transaction(function () use ($session, $fromPosition): GameSession {
            $locked = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInProgress() || $locked->current_position !== $fromPosition) {
                return $locked;
            }

            // A round that ran out of time unanswered is skipped here (if no GET did it already).
            if ($this->expire->expireLocked($locked)) {
                return $locked;
            }

            $answered = Answer::query()
                ->where('game_session_id', $locked->id)
                ->whereIn('question_id', DB::table('game_session_questions')
                    ->select('question_id')
                    ->where('game_session_id', $locked->id)
                    ->where('position', $locked->current_position))
                ->exists();

            if (! $answered) {
                throw GameException::answerRequired();
            }

            $this->moveNext->handle($locked);

            return $locked;
        });
    }
}
