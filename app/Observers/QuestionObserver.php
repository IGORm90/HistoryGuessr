<?php

namespace App\Observers;

use App\Jobs\ProcessQuestionImage;
use App\Models\Question;
use App\Services\QuestionCache;

class QuestionObserver
{
    public function __construct(private readonly QuestionCache $cache) {}

    public function created(Question $question): void
    {
        $this->dispatchProcessing($question);
    }

    public function updated(Question $question): void
    {
        if ($question->wasChanged('image_original_path')) {
            $this->dispatchProcessing($question);
        }
    }

    public function saved(Question $question): void
    {
        $this->cache->forget($question->id);
    }

    public function deleted(Question $question): void
    {
        $this->cache->forget($question->id);
    }

    private function dispatchProcessing(Question $question): void
    {
        if ($question->image_original_path !== null) {
            ProcessQuestionImage::dispatch($question->id, $question->image_original_path)->afterCommit();
        }
    }
}
