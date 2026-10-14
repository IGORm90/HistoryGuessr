<?php

namespace App\Http\Requests\Api\V1;

use App\DTO\Game\Coordinates;
use App\DTO\Game\SubmitAnswerData;
use App\Support\HistoricalYear;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Only the guess is accepted; any distance, score or duration sent by the client is ignored.
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
            // Sent when the countdown hit zero: the point (and year) are optional then.
            'timed_out' => ['sometimes', 'boolean'],
            'latitude' => ['required_unless:timed_out,true', 'required_with:longitude', 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['required_unless:timed_out,true', 'required_with:latitude', 'nullable', 'numeric', 'between:-180,180'],
            'year' => ['required_unless:timed_out,true', 'required_with:latitude', 'nullable', 'integer', 'not_in:0', 'between:'.HistoricalYear::MIN.','.HistoricalYear::MAX],
            'idempotency_key' => ['required', 'uuid'],
            // Deprecated: the server measures the time itself. Still accepted from older clients and ignored.
            'duration_ms' => ['nullable', 'integer'],
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
        // Without a point a year alone means nothing (a timeout before the player picked a place).
        $guess = $this->validated('latitude') === null ? null : new Coordinates(
            round((float) $this->validated('latitude'), 6),
            round((float) $this->validated('longitude'), 6),
        );

        return new SubmitAnswerData(
            questionPublicId: (string) $this->validated('question_id'),
            guess: $guess,
            year: $guess === null ? null : (int) $this->validated('year'),
            idempotencyKey: strtolower((string) $this->validated('idempotency_key')),
            timedOut: $this->boolean('timed_out'),
        );
    }
}
