<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class AdvanceGameRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // The position the player is leaving; makes repeated "next" clicks harmless.
            'position' => ['required', 'integer', 'min:1'],
        ];
    }
}
