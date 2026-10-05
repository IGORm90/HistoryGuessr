<?php

namespace App\Filament\Resources\Questions\Tables;

use App\Actions\Admin\AttachQuestionsToQuiz;
use App\Enums\PublicationStatus;
use App\Enums\QuestionDifficulty;
use App\Filament\Actions\PublicationActions;
use App\Filament\Resources\Questions\Actions\PreviewQuestionAction;
use App\Models\Question;
use App\Models\Quiz;
use App\Support\HistoricalYear;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class QuestionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount('quizzes'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('image_medium_path')
                    ->label('')
                    ->disk(fn (): string => config('filesystems.default'))
                    ->visibility('public')
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->label('Название')
                    ->searchable()
                    ->limit(60),
                TextColumn::make('answer_year')
                    ->label('Год')
                    ->formatStateUsing(fn (?int $state): string => $state === null ? '—' : (new HistoricalYear($state))->format())
                    ->sortable(),
                TextColumn::make('difficulty')
                    ->label('Сложность')
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge(),
                TextColumn::make('quizzes_count')
                    ->label('Подборок')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label('Изменён')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(PublicationStatus::class),
                SelectFilter::make('difficulty')
                    ->label('Сложность')
                    ->options(QuestionDifficulty::class),
                TernaryFilter::make('image_license')
                    ->label('Лицензия')
                    ->nullable()
                    ->trueLabel('Указана')
                    ->falseLabel('Не указана'),
                SelectFilter::make('quizzes')
                    ->label('Подборка')
                    ->relationship('quizzes', 'title')
                    ->preload(),
            ])
            ->recordActions([
                PreviewQuestionAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    ...PublicationActions::bulk(),
                    self::addToQuiz(),
                ]),
            ]);
    }

    private static function addToQuiz(): BulkAction
    {
        return BulkAction::make('addToQuiz')
            ->label('Добавить в подборку')
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->schema([
                Select::make('quiz_id')
                    ->label('Подборка')
                    ->options(fn () => Quiz::query()->orderBy('title')->pluck('title', 'id'))
                    ->searchable()
                    ->required(),
                TextInput::make('weight')
                    ->label('Вес при выборе в раунд')
                    ->integer()
                    ->minValue(1)
                    ->maxValue(100)
                    ->default(1)
                    ->required(),
            ])
            ->action(function (Collection $records, array $data): void {
                $quiz = Quiz::findOrFail($data['quiz_id']);
                $added = app(AttachQuestionsToQuiz::class)->handle($quiz, $records->map(fn (Question $q) => $q->id), (int) $data['weight']);

                Notification::make()->success()->title("Добавлено в «{$quiz->title}»: {$added}")->send();
            })
            ->deselectRecordsAfterCompletion();
    }
}
