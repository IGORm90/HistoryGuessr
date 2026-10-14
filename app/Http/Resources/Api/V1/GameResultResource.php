<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Answer;
use App\Models\GameSession;
use App\Models\Question;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * @mixin GameSession
 */
class GameResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Collection<int, Answer> $answers */
        $answers = $this->answers->keyBy('question_id');

        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'quiz' => ['id' => $this->quiz->public_id, 'title' => $this->quiz->title],
            'total_score' => $this->total_score,
            'max_score' => $this->question_count * 2 * (int) config('game.scoring.max_points'),
            'started_at' => $this->started_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'rounds' => $this->questions->map(function (Question $question) use ($answers): array {
                /** @var Answer|null $answer */
                $answer = $answers->get($question->id);

                return [
                    'position' => (int) $question->getRelationValue('pivot')->position,
                    'question' => [
                        'id' => $question->public_id,
                        'answer' => $question->answer,
                        'image_url' => PublicUrl::for($question->image_medium_path),
                        'location_name' => $question->location_name,
                        'year' => $question->answer_year,
                    ],
                    'timed_out' => $answer !== null && $answer->timed_out,
                    'guess' => $answer?->guessed_latitude === null ? null : [
                        'latitude' => $answer->guessed_latitude,
                        'longitude' => $answer->guessed_longitude,
                        'year' => $answer->guessed_year,
                    ],
                    'distance_meters' => $answer?->distance_meters,
                    'year_difference' => $answer?->year_difference,
                    'score' => [
                        'geo' => $answer?->geo_score,
                        'year' => $answer?->year_score,
                        'total' => $answer?->total_score,
                    ],
                ];
            })->values()->all(),
        ];
    }
}
