<?php

namespace App\DTO\Game;

final readonly class ScoreResult
{
    public function __construct(
        public int $distanceMeters,
        public int $yearDifference,
        public int $geoScore,
        public int $yearScore,
    ) {}

    public function total(): int
    {
        return $this->geoScore + $this->yearScore;
    }
}
