<?php

namespace App\Models\Concerns;

use Illuminate\Contracts\Database\Eloquent\Builder as BuilderContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
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

    /**
     * A malformed UUID must resolve to "not found" instead of a PostgreSQL type error (500).
     *
     * @param  Builder<static>|Relation<static, *, *>|BuilderContract  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return Builder<static>|Relation<static, *, *>|BuilderContract
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        if (($field ?? $this->getRouteKeyName()) === 'public_id' && ! Str::isUuid((string) $value)) {
            return $query->whereRaw('1 = 0');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }
}
