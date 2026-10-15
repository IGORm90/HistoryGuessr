<?php

namespace App\Actions\Admin;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Facades\DB;

class SyncQuestionQuizzes
{
    public function __construct(private readonly AttachQuestionsToQuiz $attach) {}

    /**
     * Makes the question belong to exactly `$quizIds`: new quizzes get it appended to the end
     * of their order, removed ones lose it, kept ones keep their position and weight.
     *
     * @param  iterable<int|string>  $quizIds
     */
    public function handle(Question $question, iterable $quizIds): void
    {
        $wanted = collect($quizIds)->map(fn (int|string $id): int => (int) $id)->unique()->values();

        DB::transaction(function () use ($question, $wanted): void {
            $current = $question->quizzes()->pluck('quizzes.id');

            $removed = $current->diff($wanted)->all();
            if ($removed !== []) {
                $question->quizzes()->detach($removed);
            }

            Quiz::query()
                ->whereKey($wanted->diff($current)->all())
                ->get()
                ->each(fn (Quiz $quiz): int => $this->attach->handle($quiz, [$question->id]));
        });
    }
}
