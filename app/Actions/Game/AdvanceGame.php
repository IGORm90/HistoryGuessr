<?php

namespace App\Actions\Game;

use App\Exceptions\GameException;
use App\Models\Answer;
use App\Models\GameSession;
use Illuminate\Support\Facades\DB;

class AdvanceGame
{
    public function __construct(private readonly CompleteGame $complete) {}

    /**
     * Moves past the answered question at `$fromPosition`; the last one completes the game.
     * Idempotent: repeating the call for a position already left (double click, retry) changes nothing.
     */
    public function handle(GameSession $session, int $fromPosition): GameSession
    {
        return DB::transaction(function () use ($session, $fromPosition): GameSession {
            $locked = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInProgress() || $locked->current_position !== $fromPosition) {
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

            if ($locked->isLastPosition()) {
                $this->complete->handle($locked);
            } else {
                $locked->increment('current_position');
            }

            return $locked;
        });
    }
}
