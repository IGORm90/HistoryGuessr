<?php

namespace App\Filament\Resources\Questions\Schemas;

use App\Actions\Admin\SyncQuestionQuizzes;
use App\Enums\PublicationStatus;
use App\Enums\QuestionDifficulty;
use App\Filament\Forms\Components\MapLocationPicker;
use App\Models\Question;
use App\Services\ImageProcessor;
use App\Support\HistoricalYear;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class QuestionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                self::basics(),
                self::image(),
                self::answer(),
                self::publication(),
            ]);
    }

    /**
     * Fields that become mandatory once the question is published (mirrors PublishQuestion).
     */
    private static function requiredWhenPublished(): \Closure
    {
        return fn (?Question $record): bool => $record?->isPublished() ?? false;
    }

    private static function basics(): Section
    {
        return Section::make('Основное')
            ->columns(2)
            ->schema([
                TextInput::make('answer')
                    ->label('Ответ')
                    ->helperText('Показывается игроку после ответа.')
                    ->required()
                    ->maxLength(255),
                Select::make('difficulty')
                    ->label('Сложность')
                    ->options(QuestionDifficulty::class)
                    ->default(QuestionDifficulty::Medium)
                    ->required(),
                self::quizzes(),
                Textarea::make('reveal_text')
                    ->label('Описание')
                    ->helperText('Показывается игроку после ответа. Обычный текст, HTML не поддерживается.')
                    ->rows(5)
                    ->maxLength(5000)
                    ->required(self::requiredWhenPublished())
                    ->columnSpanFull(),
            ]);
    }

    /**
     * Archived quizzes are offered only when the question already belongs to them,
     * so saving the form never drops them silently.
     */
    private static function quizzes(): Select
    {
        return Select::make('quizzes')
            ->label('Подборки')
            ->helperText('Вопрос добавляется в конец подборки с весом 1. Порядок и вес меняются на странице подборки.')
            ->multiple()
            ->searchable()
            ->preload()
            ->relationship(
                'quizzes',
                'title',
                fn (Builder $query, ?Question $record): Builder => $query->where(
                    fn (Builder $query): Builder => $query
                        ->where('quizzes.status', '!=', PublicationStatus::Archived)
                        ->when($record?->exists, fn (Builder $query): Builder => $query->orWhereHas(
                            'questions',
                            fn (Builder $query): Builder => $query->whereKey($record->id),
                        )),
                ),
            )
            ->saveRelationshipsUsing(fn (Question $record, ?array $state) => app(SyncQuestionQuizzes::class)->handle($record, $state ?? []))
            ->columnSpanFull();
    }

    private static function image(): Section
    {
        return Section::make('Изображение')
            ->columns(2)
            ->schema([
                FileUpload::make('image_original_path')
                    ->label('Оригинал')
                    ->helperText('JPEG, PNG или WebP до 15 МБ. Перед сохранением изображение можно обрезать (кнопка редактирования на превью). EXIF и GPS удаляются при обработке.')
                    ->image()
                    // Cropping is optional: the game shows a photo in its own proportions.
                    ->imageEditor()
                    ->imageEditorAspectRatioOptions([null, '3:2', '4:3', '16:9', '1:1'])
                    ->disk(fn (): string => config('filesystems.default'))
                    ->directory('originals')
                    ->visibility('private')
                    ->acceptedFileTypes(ImageProcessor::ALLOWED_MIME_TYPES)
                    ->maxSize(ImageProcessor::MAX_BYTES / 1024)
                    ->getUploadedFileNameForStorageUsing(
                        fn (TemporaryUploadedFile $file): string => Str::uuid().'.'.($file->guessExtension() ?? 'img'),
                    )
                    ->required(self::requiredWhenPublished())
                    ->columnSpanFull(),
                TextEntry::make('image_processing')
                    ->label('Обработка')
                    ->state(fn (?Question $record): string => match (true) {
                        $record?->image_large_path !== null => 'Готово',
                        $record?->image_original_path !== null => 'Обрабатывается… обновите страницу через несколько секунд',
                        default => 'Изображение ещё не загружено',
                    })
                    ->hiddenOn('create'),
                ImageEntry::make('image_medium_path')
                    ->label('Web-версия')
                    ->disk(fn (): string => config('filesystems.default'))
                    ->visibility('public')
                    ->imageHeight(160)
                    ->hiddenOn('create')
                    ->visible(fn (?Question $record): bool => $record?->image_medium_path !== null),
                TextInput::make('source_url')
                    ->label('Источник')
                    ->url()
                    ->maxLength(2048)
                    ->required(self::requiredWhenPublished()),
                TextInput::make('image_license')
                    ->label('Лицензия')
                    ->placeholder('Public domain, CC BY-SA 4.0…')
                    ->maxLength(255)
                    ->required(self::requiredWhenPublished()),
                TextInput::make('image_author')
                    ->label('Автор')
                    ->helperText('Если известен.')
                    ->maxLength(255),
                Textarea::make('image_attribution')
                    ->label('Текст атрибуции')
                    ->helperText('Если его требует лицензия.')
                    ->rows(2)
                    ->maxLength(1000),
            ]);
    }

    private static function answer(): Section
    {
        return Section::make('Правильный ответ')
            ->schema([
                MapLocationPicker::make('location_picker')
                    ->fields('latitude', 'longitude'),
                Grid::make(2)->schema([
                    TextInput::make('latitude')
                        ->label('Широта')
                        ->numeric()
                        ->step('any')
                        ->minValue(-90)
                        ->maxValue(90)
                        ->required(self::requiredWhenPublished()),
                    TextInput::make('longitude')
                        ->label('Долгота')
                        ->numeric()
                        ->step('any')
                        ->minValue(-180)
                        ->maxValue(180)
                        ->required(self::requiredWhenPublished()),
                ]),
                Grid::make(3)->schema([
                    // The signed `answer_year` column is edited as "number + era"; year 0 cannot be entered.
                    TextInput::make('answer_year_absolute')
                        ->label('Год')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(HistoricalYear::MAX)
                        ->dehydrated(false)
                        ->afterStateHydrated(fn (TextInput $component, ?Question $record) => $component->state(
                            $record?->answer_year === null ? null : abs($record->answer_year),
                        ))
                        ->required(self::requiredWhenPublished()),
                    Toggle::make('answer_year_bce')
                        ->label('до н. э.')
                        ->inline(false)
                        ->dehydrated(false)
                        ->afterStateHydrated(fn (Toggle $component, ?Question $record) => $component->state(
                            ($record->answer_year ?? 1) < 0,
                        )),
                    TextInput::make('location_name')
                        ->label('Название места')
                        ->helperText('Показывается игроку после ответа.')
                        ->maxLength(255)
                        ->required(self::requiredWhenPublished()),
                ]),
                Hidden::make('answer_year')
                    ->dehydrateStateUsing(function (Get $get): ?int {
                        $absolute = $get('answer_year_absolute');

                        if (blank($absolute)) {
                            return null;
                        }

                        return HistoricalYear::fromEra((int) $absolute, (bool) $get('answer_year_bce'))->value;
                    }),
            ]);
    }

    private static function publication(): Section
    {
        return Section::make('Публикация')
            ->columns(2)
            ->hiddenOn('create')
            ->schema([
                TextEntry::make('status')
                    ->label('Статус')
                    ->badge(),
                TextEntry::make('published_at')
                    ->label('Дата публикации')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—'),
            ]);
    }
}
