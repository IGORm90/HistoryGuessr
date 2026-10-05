<?php

namespace App\Filament\Resources\Quizzes\RelationManagers;

use App\Actions\Admin\AttachQuestionsToQuiz;
use App\Filament\Resources\Questions\Actions\PreviewQuestionAction;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\HistoricalYear;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * @property Quiz $ownerRecord
 */
class QuestionsRelationManager extends RelationManager
{
    protected static string $relationship = 'questions';

    protected static ?string $title = 'Вопросы';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->reorderable('position')
            ->defaultSort('quiz_questions.position')
            ->description('Порядок задаёт редакционную очередь. В игру попадают случайные опубликованные вопросы с учётом веса.')
            ->columns([
                TextColumn::make('position')->label('#'),
                ImageColumn::make('image_medium_path')
                    ->label('')
                    ->disk(fn (): string => config('filesystems.default'))
                    ->visibility('public')
                    ->imageHeight(40),
                TextColumn::make('title')->label('Название')->searchable(),
                TextColumn::make('answer_year')
                    ->label('Год')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : (new HistoricalYear($state))->format()),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('weight')->label('Вес'),
            ])
            ->headerActions([
                $this->attachQuestionsAction(),
            ])
            ->recordActions([
                PreviewQuestionAction::make(),
                $this->editWeightAction(),
                DetachAction::make()->label('Убрать'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DetachBulkAction::make()->label('Убрать из подборки'),
                ]),
            ]);
    }

    private function attachQuestionsAction(): Action
    {
        return Action::make('attachQuestions')
            ->label('Добавить вопросы')
            ->icon(Heroicon::OutlinedPlus)
            ->schema([
                Select::make('question_ids')
                    ->label('Вопросы')
                    ->multiple()
                    ->searchable()
                    ->options(fn (): array => Question::query()
                        ->whereNotIn('id', $this->ownerRecord->questions()->select('questions.id'))
                        ->orderBy('title')
                        ->pluck('title', 'id')
                        ->all())
                    ->required(),
                TextInput::make('weight')
                    ->label('Вес')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),
            ])
            ->action(function (array $data): void {
                $added = app(AttachQuestionsToQuiz::class)->handle($this->ownerRecord, $data['question_ids'], (int) $data['weight']);
                Notification::make()->success()->title("Добавлено вопросов: {$added}")->send();
            });
    }

    private function editWeightAction(): Action
    {
        return Action::make('editWeight')
            ->label('Вес')
            ->icon(Heroicon::OutlinedScale)
            ->color('gray')
            ->fillForm(fn (Question $record): array => ['weight' => $record->getRelationValue('pivot')->weight ?? 1])
            ->schema([
                TextInput::make('weight')
                    ->label('Вес при выборе в раунд')
                    ->helperText('Вопрос с весом 2 попадает в раунд примерно вдвое чаще.')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->required(),
            ])
            ->action(function (Question $record, array $data): void {
                $this->ownerRecord->questions()->updateExistingPivot($record->id, ['weight' => (int) $data['weight']]);
            });
    }
}
