<?php

namespace App\Filament\Resources\Questions\Actions;

use App\Models\Question;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Storage;

/**
 * Shows the question the way a player sees it, followed by the post-answer reveal.
 */
class PreviewQuestionAction
{
    public static function make(): Action
    {
        return Action::make('preview')
            ->label('Предпросмотр')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->modalHeading(fn (Question $record) => $record->answer)
            ->modalWidth(Width::FourExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Закрыть')
            ->modalContent(fn (Question $record) => view('filament.questions.preview', [
                'question' => $record,
                'imageUrl' => $record->image_large_path
                    ? Storage::disk(config('filesystems.default'))->url($record->image_large_path)
                    : null,
                'year' => $record->historicalYear()?->format(),
            ]));
    }
}
