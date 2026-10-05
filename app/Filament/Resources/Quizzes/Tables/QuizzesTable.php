<?php

namespace App\Filament\Resources\Quizzes\Tables;

use App\Filament\Actions\PublicationActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class QuizzesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'questions',
                'questions as published_questions_count' => fn (Builder $q) => $q->where('questions.status', 'published'),
            ]))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Название')->searchable(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('round_size')->label('В раунде')->numeric(),
                TextColumn::make('published_questions_count')
                    ->label('Опубликовано / всего')
                    ->formatStateUsing(fn (int $state, $record): string => "{$state} / {$record->questions_count}"),
                TextColumn::make('updated_at')->label('Изменена')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make(PublicationActions::bulk()),
            ]);
    }
}
