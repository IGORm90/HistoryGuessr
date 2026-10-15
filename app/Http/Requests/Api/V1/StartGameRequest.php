<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\PublicationStatus;
use App\Models\Quiz;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartGameRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'quiz_id' => [
                'nullable',
                'uuid',
                Rule::exists('quizzes', 'public_id')->where('status', PublicationStatus::Published->value),
            ],
        ];
    }

    /**
     * Null starts a game of random published questions.
     */
    public function quiz(): ?Quiz
    {
        if ($this->validated('quiz_id') === null) {
            return null;
        }

        return Quiz::query()->where('public_id', $this->validated('quiz_id'))->firstOrFail();
    }
}
