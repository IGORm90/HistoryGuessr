<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Public (CDN) URL of a processed asset on the default disk.
 */
final class PublicUrl
{
    public static function for(?string $path): ?string
    {
        return $path === null ? null : Storage::disk(config('filesystems.default'))->url($path);
    }
}
