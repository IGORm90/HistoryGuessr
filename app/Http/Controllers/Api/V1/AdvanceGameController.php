<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Game\AdvanceGame;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPlayerGame;
use App\Http\Requests\Api\V1\AdvanceGameRequest;
use App\Http\Resources\Api\V1\GameStateResource;
use App\Services\GameStateLoader;

class AdvanceGameController
{
    use ResolvesPlayerGame;

    public function __invoke(AdvanceGameRequest $request, string $gameSession, AdvanceGame $advance, GameStateLoader $states): GameStateResource
    {
        $session = $advance->handle($this->playerGame($request, $gameSession), (int) $request->validated('position'));
        $session->load('quiz:id,public_id,title');

        return new GameStateResource($states->load($session));
    }
}
