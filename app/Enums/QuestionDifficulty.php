<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum QuestionDifficulty: int implements HasColor, HasLabel
{
    case Easy = 1;
    case Medium = 2;
    case Hard = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Easy => 'Лёгкий',
            self::Medium => 'Средний',
            self::Hard => 'Сложный',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Easy => 'success',
            self::Medium => 'info',
            self::Hard => 'danger',
        };
    }
}
