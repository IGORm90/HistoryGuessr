<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Quiz;
use App\Support\PublicUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Quiz
 */
class QuizResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'slug' => $this->slug,
            'title' => $this->title,
            'description' => $this->description,
            'cover_url' => PublicUrl::for($this->cover_image_path),
            'round_size' => $this->round_size,
        ];
    }
}
