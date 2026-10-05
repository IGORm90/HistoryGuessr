<?php

namespace App\Http\Controllers\Api\V1\Concerns;

use App\Http\Middleware\EnsureAnonymousPlayer;
use App\Models\GameSession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

trait ResolvesPlayerGame
{
    /**
     * Another player's game is reported as missing rather than forbidden, so ids can't be probed.
     */
    protected function playerGame(Request $request, string $publicId): GameSession
    {
        if (! Str::isUuid($publicId)) {
            throw new NotFoundHttpException;
        }

        return GameSession::query()
            ->with('quiz:id,public_id,title')
            ->where('public_id', $publicId)
            ->where('anonymous_id', EnsureAnonymousPlayer::id($request))
            ->first() ?? throw new NotFoundHttpException;
    }
}
