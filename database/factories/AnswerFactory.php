<?php

namespace Database\Factories;

use App\Models\Answer;
use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Answer>
 */
class AnswerFactory extends Factory
{
    public function definition(): array
    {
        $geoScore = fake()->numberBetween(0, 5000);
        $yearScore = fake()->numberBetween(0, 5000);

        return [
            'game_session_id' => GameSession::factory(),
            'question_id' => Question::factory()->published(),
            'idempotency_key' => (string) Str::uuid(),
            'guessed_latitude' => fake()->latitude(),
            'guessed_longitude' => fake()->longitude(),
            'guessed_year' => fake()->numberBetween(1800, 2000),
            'distance_meters' => fake()->numberBetween(0, 20_000_000),
            'year_difference' => fake()->numberBetween(0, 200),
            'geo_score' => $geoScore,
            'year_score' => $yearScore,
            'total_score' => $geoScore + $yearScore,
            'duration_ms' => fake()->numberBetween(2_000, 60_000),
            'answered_at' => now(),
        ];
    }
}
