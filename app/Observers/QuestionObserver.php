<?php

namespace App\Observers;

use App\Jobs\ProcessQuestionImage;
use App\Models\Question;

class QuestionObserver
{
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

    private function dispatchProcessing(Question $question): void
    {
        if ($question->image_original_path !== null) {
            ProcessQuestionImage::dispatch($question->id, $question->image_original_path)->afterCommit();
        }
    }
}
