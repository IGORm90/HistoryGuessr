<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\QuizResource;
use App\Models\Quiz;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class QuizController
{
    public function index(): AnonymousResourceCollection
    {
        $quizzes = Quiz::query()
            ->published()
            ->whereHas('questions', fn (Builder $query) => $query->where('questions.status', 'published'))
            ->orderByDesc('published_at')
            ->get();

        return QuizResource::collection($quizzes);
    }
}
