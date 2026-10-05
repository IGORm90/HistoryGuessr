<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * External UUID identifier; sequential ids never leave the backend.
 */
trait HasPublicId
{
    public static function bootHasPublicId(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::uuid7();
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }
}
