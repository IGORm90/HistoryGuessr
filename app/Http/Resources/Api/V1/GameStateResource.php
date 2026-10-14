<?php

namespace App\Http\Resources\Api\V1;

use App\Services\GameState;
use App\Support\RoundClock;
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
            'round' => $this->round(),
            // Set only in the response that skipped a timed-out round.
            'notice' => $state->notice,
        ];
    }

    /**
     * Timing of the current round. The client counts down from `remaining_ms`, which does not depend
     * on its clock; `grace_ms` only tells it when to ask the server to settle a timed-out round.
     *
     * @return array<string, mixed>|null
     */
    private function round(): ?array
    {
        $state = $this->resource;
        $round = $state->round;

        if ($round === null || $state->question === null) {
            return null;
        }

        return [
            'time_limit_ms' => $state->session->round_time_limit_seconds * 1000,
            'grace_ms' => RoundClock::graceSeconds() * 1000,
            'started_at' => $round->startedAt?->toIso8601ZuluString('millisecond'),
            'deadline_at' => $round->deadlineAt?->toIso8601ZuluString('millisecond'),
            'remaining_ms' => $state->answer === null ? RoundClock::remainingMs($round->deadlineAt, now()) : null,
        ];
    }
}
