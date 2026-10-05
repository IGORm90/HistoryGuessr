<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->words(3, true);

        return [
            'title' => Str::ucfirst($title),
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(4)),
            'description' => fake()->sentence(),
            'cover_image_path' => null,
            'round_size' => 5,
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

    /**
     * Attaches `$count` published questions in position order.
     */
    public function withQuestions(int $count): static
    {
        return $this->afterCreating(function (Quiz $quiz) use ($count): void {
            $questions = Question::factory()->published()->count($count)->create();

            $quiz->questions()->attach(
                $questions->values()->mapWithKeys(fn (Question $question, int $index) => [
                    $question->id => ['position' => $index + 1, 'weight' => 1],
                ])->all(),
            );
        });
    }
}
