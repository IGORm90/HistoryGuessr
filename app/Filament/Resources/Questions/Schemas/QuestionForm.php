<?php

namespace App\Filament\Resources\Questions\Schemas;

use App\Enums\QuestionDifficulty;
use App\Filament\Forms\Components\LeafletLocationPicker;
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
                self::reveal(),
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
                TextInput::make('title')
                    ->label('Внутреннее название')
                    ->required()
                    ->maxLength(255),
                Select::make('difficulty')
                    ->label('Сложность')
                    ->options(QuestionDifficulty::class)
                    ->default(QuestionDifficulty::Medium)
                    ->required(),
                Textarea::make('prompt')
                    ->label('Вопрос игроку')
                    ->helperText('Обычный текст, HTML не поддерживается.')
                    ->rows(3)
                    ->maxLength(1000)
                    ->required(self::requiredWhenPublished())
                    ->columnSpanFull(),
            ]);
    }

    private static function image(): Section
    {
        return Section::make('Изображение')
            ->columns(2)
            ->schema([
                FileUpload::make('image_original_path')
                    ->label('Оригинал')
                    ->helperText('JPEG, PNG или WebP до 15 МБ. EXIF и GPS удаляются при обработке.')
                    ->image()
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
                LeafletLocationPicker::make('location_picker')
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

    private static function reveal(): Section
    {
        return Section::make('После ответа')
            ->schema([
                Textarea::make('reveal_text')
                    ->label('Историческое пояснение')
                    ->helperText('Обычный текст, HTML не поддерживается.')
                    ->rows(5)
                    ->maxLength(5000)
                    ->required(self::requiredWhenPublished()),
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
