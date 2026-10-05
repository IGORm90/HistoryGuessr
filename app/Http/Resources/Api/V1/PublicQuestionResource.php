<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Question;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What the player may see BEFORE answering. Never add answer fields here
 * (coordinates, year, location name, reveal text) — they belong to AnswerRevealResource.
 *
 * @mixin Question
 */
class PublicQuestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'prompt' => $this->prompt,
            'image' => [
                'medium_url' => PublicUrl::for($this->image_medium_path),
                'large_url' => PublicUrl::for($this->image_large_path),
                'width' => $this->image_width,
                'height' => $this->image_height,
            ],
        ];
    }
}
