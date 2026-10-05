<?php

namespace App\Support;

use InvalidArgumentException;
use Stringable;

/**
 * A calendar year stored as a signed integer: -44 is 44 BC, 1921 is AD 1921.
 * Year 0 does not exist, so 1 BC is directly followed by AD 1.
 */
final readonly class HistoricalYear implements Stringable
{
    public const int MIN = -10000;

    public const int MAX = 2100;

    public function __construct(public int $value)
    {
        if (! self::isValid($value)) {
            throw new InvalidArgumentException("Invalid historical year: {$value}");
        }
    }

    public static function isValid(int $value): bool
    {
        return $value !== 0 && $value >= self::MIN && $value <= self::MAX;
    }

    /**
     * Builds a signed year from the "number + era" pair used in forms.
     */
    public static function fromEra(int $absoluteYear, bool $beforeCommonEra): self
    {
        if ($absoluteYear < 1) {
            throw new InvalidArgumentException("Absolute year must be positive, got {$absoluteYear}");
        }

        return new self($beforeCommonEra ? -$absoluteYear : $absoluteYear);
    }

    /**
     * Number of calendar years between two signed years, skipping the non-existent year 0.
     */
    public static function difference(int $a, int $b): int
    {
        $diff = abs($a - $b);

        return ($a < 0) !== ($b < 0) ? $diff - 1 : $diff;
    }

    public function isBeforeCommonEra(): bool
    {
        return $this->value < 0;
    }

    public function absolute(): int
    {
        return abs($this->value);
    }

    public function diff(self $other): int
    {
        return self::difference($this->value, $other->value);
    }

    public function format(): string
    {
        return $this->isBeforeCommonEra()
            ? "{$this->absolute()} г. до н. э."
            : "{$this->value} г.";
    }

    public function __toString(): string
    {
        return $this->format();
    }
}
