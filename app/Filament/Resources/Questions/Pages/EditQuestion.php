<?php

namespace App\Filament\Resources\Questions\Pages;

use App\Filament\Actions\PublicationActions;
use App\Filament\Resources\Questions\Actions\DeleteQuestionAction;
use App\Filament\Resources\Questions\Actions\PreviewQuestionAction;
use App\Filament\Resources\Questions\QuestionResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\EditRecord;

class EditQuestion extends EditRecord
{
    protected static string $resource = QuestionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PreviewQuestionAction::make(),
            PublicationActions::publish(),
            ActionGroup::make([
                PublicationActions::toDraft(),
                PublicationActions::archive(),
                DeleteQuestionAction::make(),
            ]),
        ];
    }
}
