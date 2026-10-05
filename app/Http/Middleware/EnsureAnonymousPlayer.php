<?php

namespace App\Http\Middleware;

use App\Exceptions\GameException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the browser session to an anonymous player id; game sessions belong to that id.
 */
class EnsureAnonymousPlayer
{
    public const string SESSION_KEY = 'anonymous_player_id';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            throw GameException::sessionRequired();
        }

        if (! Str::isUuid((string) $request->session()->get(self::SESSION_KEY))) {
            $request->session()->put(self::SESSION_KEY, (string) Str::uuid());
        }

        return $next($request);
    }

    public static function id(Request $request): string
    {
        return (string) $request->session()->get(self::SESSION_KEY);
    }
}
