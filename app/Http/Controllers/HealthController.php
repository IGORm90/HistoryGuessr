<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthController
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'redis' => $this->check(fn () => Redis::connection()->ping()),
            // Read-only probe: no writes on every health request.
            'storage' => $this->check(fn () => Storage::disk(config('filesystems.default'))->exists('.health')),
        ];

        $healthy = ! in_array(false, $checks, true);

        return new JsonResponse(
            ['status' => $healthy ? 'ok' : 'fail', 'checks' => array_map(fn (bool $ok) => $ok ? 'ok' : 'fail', $checks)],
            $healthy ? 200 : 503,
        );
    }

    private function check(callable $probe): bool
    {
        try {
            $probe();

            return true;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
