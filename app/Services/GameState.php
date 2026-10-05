<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\GameSession;
use App\Models\Question;

/**
 * Read model of a game as the player currently sees it.
 */
final readonly class GameState
{
    public function __construct(
        public GameSession $session,
        public ?Question $question,
        public ?Answer $answer,
    ) {}
}
