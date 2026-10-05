<?php

use Illuminate\Support\Facades\Redis;

it('reports healthy when all dependencies respond', function () {
    Redis::shouldReceive('connection->ping')->andReturn(true);

    $this->getJson('/health')
        ->assertOk()
        ->assertExactJson([
            'status' => 'ok',
            'checks' => ['database' => 'ok', 'redis' => 'ok', 'storage' => 'ok'],
        ]);
});

it('returns 503 when a dependency fails', function () {
    Redis::shouldReceive('connection->ping')->andThrow(new RuntimeException('redis down'));

    $this->getJson('/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'fail')
        ->assertJsonPath('checks.redis', 'fail');
});
