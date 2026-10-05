<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Game\StartGame;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPlayerGame;
use App\Http\Middleware\EnsureAnonymousPlayer;
use App\Http\Requests\Api\V1\StartGameRequest;
use App\Http\Resources\Api\V1\GameStateResource;
use App\Services\GameStateLoader;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameSessionController
{
    use ResolvesPlayerGame;

    public function __construct(private readonly GameStateLoader $states) {}

    public function store(StartGameRequest $request, StartGame $startGame): JsonResponse
    {
        $session = $startGame->handle($request->quiz(), EnsureAnonymousPlayer::id($request));
        $session->load('quiz:id,public_id,title');

        return (new GameStateResource($this->states->load($session)))->response()->setStatusCode(201);
    }

    public function show(Request $request, string $gameSession): GameStateResource
    {
        return new GameStateResource($this->states->load($this->playerGame($request, $gameSession)));
    }
}
