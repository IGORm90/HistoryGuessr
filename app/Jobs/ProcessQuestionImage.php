<?php

namespace App\Jobs;

use App\Exceptions\InvalidImageException;
use App\Models\Question;
use App\Services\ImageProcessor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessQuestionImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(
        public readonly int $questionId,
        public readonly string $originalPath,
    ) {}

    public function handle(ImageProcessor $processor): void
    {
        $question = Question::find($this->questionId);

        // The original was replaced or removed after dispatch: a newer job owns the result.
        if ($question === null || $question->image_original_path !== $this->originalPath) {
            return;
        }

        $disk = Storage::disk(config('filesystems.default'));

        try {
            $result = $processor->processQuestionImage($disk, $this->originalPath);
        } catch (InvalidImageException $e) {
            Log::warning('Question image rejected', ['question_id' => $question->id, 'reason' => $e->getMessage()]);
            $this->fail($e);

            return;
        }

        $previous = array_filter([$question->image_large_path, $question->image_medium_path]);

        $question->forceFill([
            'image_large_path' => $result->largePath,
            'image_medium_path' => $result->mediumPath,
            'image_width' => $result->width,
            'image_height' => $result->height,
        ])->saveQuietly();

        $disk->delete($previous);
    }
}
