<?php

namespace App\Services;

use App\Models\Question;
use Illuminate\Support\Facades\Cache;

/**
 * Read-through cache of question rows used by the game flow (public data and the answer).
 * Invalidated by QuestionObserver on save/delete.
 */
class QuestionCache
{
    public function get(int $id): ?Question
    {
        /** @var array<string, mixed>|null $attributes */
        $attributes = Cache::remember(
            self::key($id),
            (int) config('game.question_cache_ttl'),
            fn (): ?array => Question::query()->find($id)?->getAttributes(),
        );

        return $attributes === null ? null : (new Question)->newFromBuilder($attributes);
    }

    public function forget(int $id): void
    {
        Cache::forget(self::key($id));
    }

    private static function key(int $id): string
    {
        return "game:question:{$id}";
    }
}
