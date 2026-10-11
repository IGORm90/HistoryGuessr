<?php

namespace App\Filament\Actions;

use App\Actions\Admin\ChangePublicationStatus;
use App\Actions\Admin\PublishQuestion;
use App\Actions\Admin\PublishQuiz;
use App\Enums\PublicationStatus;
use App\Exceptions\PublicationException;
use App\Models\Question;
use App\Models\Quiz;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Component;

/**
 * Publish / return to draft / archive for questions and quizzes, as record and bulk actions.
 * Publishing always goes through PublishQuestion / PublishQuiz so the completeness checks apply.
 */
class PublicationActions
{
    public static function publish(): Action
    {
        return Action::make('publish')
            ->label('Опубликовать')
            ->icon(Heroicon::OutlinedCheckCircle)
            ->color('success')
            ->visible(fn (Question|Quiz $record) => ! $record->isPublished())
            ->action(function (Question|Quiz $record, Component $livewire): void {
                try {
                    self::publishRecord($record);
                } catch (PublicationException $e) {
                    Notification::make()->danger()->title('Нельзя опубликовать')->body(implode("\n", $e->problems))->send();

                    return;
                }

                Notification::make()->success()->title('Опубликовано')->send();
                self::refreshForm($livewire);
            });
    }

    public static function toDraft(): Action
    {
        return self::statusAction('toDraft', PublicationStatus::Draft, 'В черновик', Heroicon::OutlinedPencilSquare, 'gray');
    }

    public static function archive(): Action
    {
        return self::statusAction('archive', PublicationStatus::Archived, 'В архив', Heroicon::OutlinedArchiveBox, 'warning')
            ->requiresConfirmation();
    }

    /**
     * @return list<BulkAction>
     */
    public static function bulk(): array
    {
        return [
            BulkAction::make('publishSelected')
                ->label('Опубликовать')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->action(function (Collection $records): void {
                    $failed = [];
                    foreach ($records as $record) {
                        try {
                            self::publishRecord($record);
                        } catch (PublicationException $e) {
                            $failed[] = "«{$record->answer}»: ".implode('; ', $e->problems);
                        }
                    }

                    $published = $records->count() - count($failed);
                    $notification = Notification::make()->title("Опубликовано: {$published} из {$records->count()}");
                    ($failed === [] ? $notification->success() : $notification->warning()->body(implode("\n", $failed)))->send();
                })
                ->deselectRecordsAfterCompletion(),
            self::bulkStatusAction('draftSelected', PublicationStatus::Draft, 'В черновик', Heroicon::OutlinedPencilSquare),
            self::bulkStatusAction('archiveSelected', PublicationStatus::Archived, 'В архив', Heroicon::OutlinedArchiveBox)
                ->requiresConfirmation(),
        ];
    }

    private static function publishRecord(Question|Quiz $record): void
    {
        $record instanceof Question
            ? app(PublishQuestion::class)->handle($record)
            : app(PublishQuiz::class)->handle($record);
    }

    private static function statusAction(string $name, PublicationStatus $status, string $label, Heroicon $icon, string $color): Action
    {
        return Action::make($name)
            ->label($label)
            ->icon($icon)
            ->color($color)
            ->visible(fn (Question|Quiz $record) => $record->status !== $status)
            ->action(function (Question|Quiz $record, Component $livewire) use ($status): void {
                app(ChangePublicationStatus::class)->handle($record, $status);
                Notification::make()->success()->title("Статус: {$status->getLabel()}")->send();
                self::refreshForm($livewire);
            });
    }

    private static function bulkStatusAction(string $name, PublicationStatus $status, string $label, Heroicon $icon): BulkAction
    {
        return BulkAction::make($name)
            ->label($label)
            ->icon($icon)
            ->action(function (Collection $records) use ($status): void {
                $records->each(fn (Question|Quiz $record) => app(ChangePublicationStatus::class)->handle($record, $status));
                Notification::make()->success()->title("Статус «{$status->getLabel()}»: {$records->count()}")->send();
            })
            ->deselectRecordsAfterCompletion();
    }

    private static function refreshForm(Component $livewire): void
    {
        if ($livewire instanceof EditRecord) {
            $livewire->refreshFormData(['status', 'published_at']);
        }
    }
}
