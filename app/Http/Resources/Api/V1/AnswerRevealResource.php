<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Answer;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The scored guess plus the correct answer. Only returned once the answer exists.
 *
 * @property Answer $resource
 */
class AnswerRevealResource extends JsonResource
{
    public function __construct(Answer $answer, private readonly Question $question)
    {
        parent::__construct($answer);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $answer = $this->resource;
        $question = $this->question;

        return [
            'question_id' => $question->public_id,
            'guess' => [
                'latitude' => $answer->guessed_latitude,
                'longitude' => $answer->guessed_longitude,
                'year' => $answer->guessed_year,
            ],
            'correct' => [
                'latitude' => $question->latitude,
                'longitude' => $question->longitude,
                'year' => $question->answer_year,
                'answer' => $question->answer,
                'location_name' => $question->location_name,
            ],
            'distance_meters' => $answer->distance_meters,
            'year_difference' => $answer->year_difference,
            'score' => [
                'geo' => $answer->geo_score,
                'year' => $answer->year_score,
                'total' => $answer->total_score,
            ],
            'reveal_text' => $question->reveal_text,
            'attribution' => [
                'author' => $question->image_author,
                'license' => $question->image_license,
                'source_url' => $question->source_url,
                'text' => $question->image_attribution,
            ],
        ];
    }
}
