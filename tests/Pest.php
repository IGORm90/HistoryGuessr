<?php

use App\Models\Quiz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)
    ->in('Unit');

// Game API helpers; callers send `Referer: APP_URL` so Sanctum starts the player's session.

function startGame(Quiz $quiz): array
{
    return test()->postJson('/api/v1/game-sessions', ['quiz_id' => $quiz->public_id])->assertCreated()->json();
}

function answerCurrent(string $gameId, array $overrides = []): TestResponse
{
    $state = test()->getJson("/api/v1/game-sessions/{$gameId}")->json();

    return test()->postJson("/api/v1/game-sessions/{$gameId}/answers", array_merge([
        'question_id' => $state['question']['id'],
        'latitude' => 48.8566,
        'longitude' => 2.3522,
        'year' => 1900,
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides));
}

function advance(string $gameId, int $position): TestResponse
{
    return test()->postJson("/api/v1/game-sessions/{$gameId}/advance", ['position' => $position]);
}
