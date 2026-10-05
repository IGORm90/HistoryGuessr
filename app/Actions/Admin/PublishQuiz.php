<?php

namespace App\Actions\Admin;

use App\Enums\PublicationStatus;
use App\Exceptions\PublicationException;
use App\Models\Quiz;

class PublishQuiz
{
    public function handle(Quiz $quiz): Quiz
    {
        $problems = $this->problems($quiz);

        if ($problems !== []) {
            throw new PublicationException($problems);
        }

        if (! $quiz->isPublished()) {
            $quiz->update([
                'status' => PublicationStatus::Published,
                'published_at' => $quiz->published_at ?? now(),
            ]);
        }

        return $quiz;
    }

    /**
     * @return list<string>
     */
    public function problems(Quiz $quiz): array
    {
        $published = $quiz->questions()->published()->count();

        if ($published < $quiz->round_size) {
            return ["опубликованных вопросов {$published}, а в раунде {$quiz->round_size}"];
        }

        return [];
    }
}
