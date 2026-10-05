<?php

namespace App\Actions\Admin;

use App\Models\Quiz;
use Illuminate\Support\Facades\DB;

class AttachQuestionsToQuiz
{
    /**
     * Appends questions to the end of the quiz order, skipping ones already attached.
     *
     * @param  iterable<int>  $questionIds
     * @return int Number of newly attached questions.
     */
    public function handle(Quiz $quiz, iterable $questionIds, int $weight = 1): int
    {
        return DB::transaction(function () use ($quiz, $questionIds, $weight): int {
            // Lock the quiz row so concurrent attaches don't compute the same positions.
            Quiz::query()->whereKey($quiz->id)->lockForUpdate()->first();

            $existing = DB::table('quiz_questions')->where('quiz_id', $quiz->id)->pluck('question_id')->all();
            $position = (int) DB::table('quiz_questions')->where('quiz_id', $quiz->id)->max('position');

            $attach = [];
            foreach (collect($questionIds)->unique() as $questionId) {
                if (! in_array($questionId, $existing, false)) {
                    $attach[(int) $questionId] = ['position' => ++$position, 'weight' => max(1, $weight)];
                }
            }

            $quiz->questions()->attach($attach);

            return count($attach);
        });
    }
}
