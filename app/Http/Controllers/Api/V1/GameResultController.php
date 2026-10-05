<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\GameException;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPlayerGame;
use App\Http\Resources\Api\V1\GameResultResource;
use Illuminate\Http\Request;

class GameResultController
{
    use ResolvesPlayerGame;

    public function __invoke(Request $request, string $gameSession): GameResultResource
    {
        $session = $this->playerGame($request, $gameSession);

        if ($session->isInProgress()) {
            throw GameException::notCompleted();
        }

        return new GameResultResource($session->load(['questions', 'answers']));
    }
}
