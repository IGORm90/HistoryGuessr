<?php

namespace App\Actions\Game;

use App\Enums\GameSessionStatus;
use App\Models\GameSession;

class CompleteGame
{
    public function handle(GameSession $session): void
    {
        $session->update([
            'status' => GameSessionStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
