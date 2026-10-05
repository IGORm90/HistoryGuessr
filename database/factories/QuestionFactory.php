<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Enums\QuestionDifficulty;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Default state is a complete draft; use published() for playable questions.
 *
 * @extends Factory<Question>
 */
class QuestionFactory extends Factory
{
    public function definition(): array
    {
        $key = (string) Str::uuid();

        return [
            'title' => fake()->sentence(3),
            'prompt' => fake()->sentence(12),
            // No original: factories model already-processed images and must not trigger the processing job.
            'image_original_path' => null,
            'image_large_path' => "questions/{$key}/large.webp",
            'image_medium_path' => "questions/{$key}/medium.webp",
            'image_width' => 1600,
            'image_height' => 1067,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'answer_year' => fake()->numberBetween(1850, 2000),
            'location_name' => fake()->city(),
            'reveal_text' => fake()->paragraph(),
            'source_url' => fake()->url(),
            'image_author' => fake()->name(),
            'image_license' => 'Public domain',
            'image_attribution' => null,
            'difficulty' => fake()->randomElement(QuestionDifficulty::cases()),
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PublicationStatus::Archived,
        ]);
    }

    public function bce(): static
    {
        return $this->state(fn (array $attributes) => [
            'answer_year' => -fake()->numberBetween(1, 3000),
        ]);
    }

    /** A freshly created draft: no image, no answer, no attribution yet. */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'prompt' => null,
            'image_original_path' => null,
            'image_large_path' => null,
            'image_medium_path' => null,
            'image_width' => null,
            'image_height' => null,
            'latitude' => null,
            'longitude' => null,
            'answer_year' => null,
            'location_name' => null,
            'reveal_text' => null,
            'source_url' => null,
            'image_license' => null,
        ]);
    }
}
