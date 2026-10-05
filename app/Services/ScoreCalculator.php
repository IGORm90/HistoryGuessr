<?php

namespace App\Services;

use App\DTO\Game\Coordinates;
use App\DTO\Game\ScoreResult;
use App\Support\HistoricalYear;

class ScoreCalculator
{
    public function __construct(
        private readonly DistanceCalculator $distance,
        private readonly int $maxPoints,
        private readonly float $geoScaleKm,
        private readonly float $yearScale,
    ) {}

    public static function fromConfig(DistanceCalculator $distance): self
    {
        return new self(
            $distance,
            (int) config('game.scoring.max_points'),
            (float) config('game.scoring.geo_scale_km'),
            (float) config('game.scoring.year_scale'),
        );
    }

    public function score(Coordinates $guess, int $guessedYear, Coordinates $answer, int $answerYear): ScoreResult
    {
        $distanceMeters = $this->distance->meters($guess, $answer);
        $yearDifference = HistoricalYear::difference($guessedYear, $answerYear);

        return new ScoreResult(
            distanceMeters: $distanceMeters,
            yearDifference: $yearDifference,
            geoScore: $this->decay($distanceMeters / 1000, $this->geoScaleKm),
            yearScore: $this->decay($yearDifference, $this->yearScale),
        );
    }

    public function maxPerQuestion(): int
    {
        return $this->maxPoints * 2;
    }

    private function decay(float $error, float $scale): int
    {
        return (int) round($this->maxPoints * exp(-$error / $scale));
    }
}
