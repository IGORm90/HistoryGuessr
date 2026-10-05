<?php

namespace App\Filament\Resources\Quizzes\Schemas;

use App\Exceptions\InvalidImageException;
use App\Models\Quiz;
use App\Services\ImageProcessor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class QuizForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Подборка')
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, Set $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug')
                            ->required()
                            ->maxLength(255)
                            ->alphaDash()
                            ->unique(ignoreRecord: true),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),
                        TextInput::make('round_size')
                            ->label('Вопросов в раунде')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(50)
                            ->default(5)
                            ->required(),
                        FileUpload::make('cover_image_path')
                            ->label('Обложка')
                            ->helperText('Перекодируется в WebP, EXIF удаляется.')
                            ->image()
                            ->disk(fn (): string => config('filesystems.default'))
                            ->visibility('public')
                            ->acceptedFileTypes(ImageProcessor::ALLOWED_MIME_TYPES)
                            ->maxSize(ImageProcessor::MAX_BYTES / 1024)
                            ->saveUploadedFileUsing(fn (TemporaryUploadedFile $file): string => self::storeCover($file)),
                    ]),
                Section::make('Публикация')
                    ->columns(2)
                    ->hiddenOn('create')
                    ->schema([
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('published_questions')
                            ->label('Опубликованных вопросов')
                            ->state(fn (Quiz $record): string => $record->questions()->published()->count()." (нужно {$record->round_size})"),
                    ]),
            ]);
    }

    /**
     * Covers are small and admin-only, so they are processed synchronously instead of via the queue.
     */
    private static function storeCover(TemporaryUploadedFile $file): string
    {
        try {
            $webp = app(ImageProcessor::class)->processToWebp((string) $file->get(), 1200);
        } catch (InvalidImageException) {
            throw ValidationException::withMessages(['data.cover_image_path' => 'Не удалось обработать изображение.']);
        }

        $path = 'quizzes/'.Str::uuid().'.webp';
        Storage::disk(config('filesystems.default'))->put($path, $webp, 'public');

        return $path;
    }
}
