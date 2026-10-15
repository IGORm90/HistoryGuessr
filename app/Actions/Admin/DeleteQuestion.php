<?php

namespace App\Actions\Admin;

use App\Enums\PublicationStatus;
use App\Models\GameSession;
use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Permanently deletes an archived question. Games that included it are deleted too (their rounds
 * and answers cascade): otherwise their question_count and total_score would no longer add up.
 * The image files are removed once the transaction commits.
 */
class DeleteQuestion
{
    public function handle(Question $question): void
    {
        if ($question->status !== PublicationStatus::Archived) {
            throw new \InvalidArgumentException('Only archived questions can be deleted.');
        }

        $paths = array_values(array_filter([
            $question->image_original_path,
            $question->image_large_path,
            $question->image_medium_path,
        ]));

        DB::transaction(function () use ($question, $paths): void {
            $this->affectedGames($question)->delete();
            $question->delete();

            DB::afterCommit(fn () => Storage::disk(config('filesystems.default'))->delete($paths));
        });
    }

    public function affectedGamesCount(Question $question): int
    {
        return $this->affectedGames($question)->count();
    }

    /**
     * @return Builder<GameSession>
     */
    private function affectedGames(Question $question): Builder
    {
        return GameSession::query()
            ->whereHas('questions', fn (Builder $q) => $q->whereKey($question->id))
            ->orWhereHas('answers', fn (Builder $q) => $q->where('question_id', $question->id));
    }
}
