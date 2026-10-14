<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * The one rule for round time: a round is expired once `now > deadline + grace` and it has no answer.
 * A round without a deadline (started before the timer existed) never expires.
 */
final class RoundClock
{
    public static function limitSeconds(): int
    {
        return max(1, (int) config('game.round.time_limit'));
    }

    public static function graceSeconds(): int
    {
        return max(0, (int) config('game.round.grace'));
    }

    public static function deadline(CarbonInterface $startedAt, int $limitSeconds): CarbonImmutable
    {
        return CarbonImmutable::instance($startedAt)->addSeconds($limitSeconds);
    }

    public static function isExpired(?CarbonInterface $deadline, CarbonInterface $now): bool
    {
        return $deadline !== null && $now->greaterThan($deadline->copy()->addSeconds(self::graceSeconds()));
    }

    /**
     * Milliseconds left until the deadline the player sees (the grace period is not included).
     */
    public static function remainingMs(?CarbonInterface $deadline, CarbonInterface $now): ?int
    {
        if ($deadline === null) {
            return null;
        }

        return max(0, $deadline->getTimestampMs() - $now->getTimestampMs());
    }

    /**
     * Formats a round time for the query builder, keeping milliseconds (its default format drops them).
     */
    public static function toDatabase(CarbonInterface $time): string
    {
        return $time->format('Y-m-d H:i:s.v');
    }

    /**
     * Parses a raw timestamp column read through the query builder.
     */
    public static function parse(?string $value): ?CarbonImmutable
    {
        return $value === null ? null : CarbonImmutable::parse($value);
    }
}
