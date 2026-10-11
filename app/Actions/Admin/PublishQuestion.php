<?php

namespace App\Actions\Admin;

use App\Enums\PublicationStatus;
use App\Exceptions\PublicationException;
use App\Models\Question;

class PublishQuestion
{
    /**
     * Required for publication, with labels shown to editors.
     */
    private const array REQUIRED = [
        'latitude' => 'широта',
        'longitude' => 'долгота',
        'answer_year' => 'год',
        'location_name' => 'название места',
        'reveal_text' => 'описание',
        'source_url' => 'ссылка на источник',
        'image_license' => 'лицензия изображения',
    ];

    public function handle(Question $question): Question
    {
        $problems = $this->problems($question);

        if ($problems !== []) {
            throw new PublicationException($problems);
        }

        if (! $question->isPublished()) {
            $question->update([
                'status' => PublicationStatus::Published,
                'published_at' => $question->published_at ?? now(),
            ]);
        }

        return $question;
    }

    /**
     * @return list<string>
     */
    public function problems(Question $question): array
    {
        $problems = [];

        if ($question->image_large_path === null || $question->image_medium_path === null) {
            $problems[] = $question->image_original_path === null
                ? 'не загружено изображение'
                : 'изображение ещё обрабатывается';
        }

        $missing = array_keys(array_filter(
            self::REQUIRED,
            fn (string $label, string $attribute) => blank($question->getAttribute($attribute)),
            ARRAY_FILTER_USE_BOTH,
        ));

        if ($missing !== []) {
            $problems[] = 'не заполнено: '.implode(', ', array_map(fn (string $a) => self::REQUIRED[$a], $missing));
        }

        return $problems;
    }
}
