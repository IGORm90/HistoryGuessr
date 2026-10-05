<?php

namespace Database\Factories;

use App\Enums\GameSessionStatus;
use App\Models\GameSession;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Creates a session row only; StartGame (stage 4) is responsible for fixing the question order.
 *
 * @extends Factory<GameSession>
 */
class GameSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory()->published(),
            'anonymous_id' => (string) Str::uuid(),
            'status' => GameSessionStatus::InProgress,
            'current_position' => 1,
            'question_count' => 5,
            'total_score' => 0,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameSessionStatus::Completed,
            'current_position' => $attributes['question_count'] ?? 5,
            'completed_at' => now(),
        ]);
    }
}
