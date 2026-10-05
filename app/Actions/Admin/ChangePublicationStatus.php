<?php

namespace App\Actions\Admin;

use App\Enums\PublicationStatus;
use App\Models\Question;
use App\Models\Quiz;

/**
 * Moves a question or quiz back to draft or to the archive. Publishing goes through PublishQuestion / PublishQuiz.
 */
class ChangePublicationStatus
{
    public function handle(Question|Quiz $model, PublicationStatus $status): void
    {
        if ($status === PublicationStatus::Published) {
            throw new \InvalidArgumentException('Use PublishQuestion or PublishQuiz to publish.');
        }

        $model->update(['status' => $status]);
    }
}
