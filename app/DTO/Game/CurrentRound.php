<?php

namespace App\DTO\Game;

use App\Support\RoundClock;
use Carbon\CarbonImmutable;

/**
 * The question at the game's current position and when its round started and ends.
 */
final readonly class CurrentRound
{
    public function __construct(
        public int $questionId,
        public string $questionPublicId,
        public ?CarbonImmutable $startedAt,
        public ?CarbonImmutable $deadlineAt,
    ) {}

    public function isExpired(CarbonImmutable $now): bool
    {
        return RoundClock::isExpired($this->deadlineAt, $now);
    }
}
