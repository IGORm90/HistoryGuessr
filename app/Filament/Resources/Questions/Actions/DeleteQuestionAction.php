<?php

namespace App\Filament\Resources\Questions\Actions;

use App\Actions\Admin\DeleteQuestion;
use App\Enums\PublicationStatus;
use App\Models\Question;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;

/**
 * Deletion of archived questions (the policy hides it for the rest), together with the games they were played in.
 */
class DeleteQuestionAction
{
    public static function make(): DeleteAction
    {
        return DeleteAction::make()
            ->modalDescription(function (Question $record): string {
                $games = app(DeleteQuestion::class)->affectedGamesCount($record);

                return $games === 0
                    ? 'Вопрос и его фотографии будут удалены безвозвратно.'
                    : "Вопрос, его фотографии и игры, в которых он был ({$games}), будут удалены безвозвратно.";
            })
            ->using(function (Question $record): bool {
                app(DeleteQuestion::class)->handle($record);

                return true;
            });
    }

    public static function bulk(): BulkAction
    {
        return BulkAction::make('deleteSelected')
            ->label('Удалить из архива')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('Будут удалены только вопросы из архива — вместе с фотографиями и играми, в которых они были.')
            ->action(function (Collection $records): void {
                $archived = $records->filter(fn (Question $q) => $q->status === PublicationStatus::Archived);
                $archived->each(fn (Question $q) => app(DeleteQuestion::class)->handle($q));

                $skipped = $records->count() - $archived->count();
                $notification = Notification::make()->title("Удалено: {$archived->count()} из {$records->count()}");
                ($skipped === 0 ? $notification->success() : $notification->warning()->body("Не в архиве: {$skipped}"))->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
