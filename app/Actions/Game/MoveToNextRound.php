<?php

namespace App\Actions\Game;

use App\Models\GameSession;

class MoveToNextRound
{
    public function __construct(private readonly CompleteGame $complete) {}

    /**
     * Shows the next question and starts its timer, or completes the game after the last one.
     * The caller holds the session row lock and has checked the current question is settled.
     */
    public function handle(GameSession $locked): void
    {
        if ($locked->isLastPosition()) {
            $this->complete->handle($locked);

            return;
        }

        $locked->increment('current_position');
        $locked->startCurrentRound();
    }
}
