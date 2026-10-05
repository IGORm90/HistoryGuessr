<?php

namespace App\Http\Resources\Api\V1;

use App\Services\GameState;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property GameState $resource
 */
class GameStateResource extends JsonResource
{
    public function __construct(GameState $state)
    {
        parent::__construct($state);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $state = $this->resource;
        $session = $state->session;

        return [
            'id' => $session->public_id,
            'status' => $session->status->value,
            'quiz' => [
                'id' => $session->quiz->public_id,
                'title' => $session->quiz->title,
            ],
            'position' => $session->current_position,
            'question_count' => $session->question_count,
            'total_score' => $session->total_score,
            'max_score' => $session->question_count * 2 * (int) config('game.scoring.max_points'),
            'question' => $state->question ? new PublicQuestionResource($state->question) : null,
            // Present only after the current question has been answered.
            'answer' => $state->answer && $state->question ? new AnswerRevealResource($state->answer, $state->question) : null,
        ];
    }
}
