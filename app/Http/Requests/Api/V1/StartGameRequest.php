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
                'required',
                'uuid',
                Rule::exists('quizzes', 'public_id')->where('status', PublicationStatus::Published->value),
            ],
        ];
    }

    public function quiz(): Quiz
    {
        return Quiz::query()->where('public_id', $this->validated('quiz_id'))->firstOrFail();
    }
}
