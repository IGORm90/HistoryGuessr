<?php

namespace App\Http\Requests\Api\V1;

use App\DTO\Game\Coordinates;
use App\DTO\Game\SubmitAnswerData;
use App\Support\HistoricalYear;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the guess is accepted; any distance or score sent by the client is ignored.
 */
class SubmitAnswerRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'question_id' => ['required', 'uuid'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'year' => ['required', 'integer', 'not_in:0', 'between:'.HistoricalYear::MIN.','.HistoricalYear::MAX],
            'idempotency_key' => ['required', 'uuid'],
            'duration_ms' => ['nullable', 'integer', 'between:0,86400000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['year.not_in' => 'Года 0 не существует.'];
    }

    public function toData(): SubmitAnswerData
    {
        return new SubmitAnswerData(
            questionPublicId: (string) $this->validated('question_id'),
            guess: new Coordinates(
                round((float) $this->validated('latitude'), 6),
                round((float) $this->validated('longitude'), 6),
            ),
            year: (int) $this->validated('year'),
            idempotencyKey: strtolower((string) $this->validated('idempotency_key')),
            durationMs: $this->validated('duration_ms') === null ? null : (int) $this->validated('duration_ms'),
        );
    }
}
