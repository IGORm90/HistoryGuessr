<?php

use App\DTO\Game\Coordinates;
use App\Services\DistanceCalculator;
use App\Services\ScoreCalculator;

beforeEach(function () {
    $this->calculator = new ScoreCalculator(new DistanceCalculator, maxPoints: 5000, geoScaleKm: 2000, yearScale: 25);
    $this->rome = new Coordinates(41.895466, 12.476768);
});

it('awards the maximum for an exact guess', function () {
    $score = $this->calculator->score($this->rome, 1921, $this->rome, 1921);

    expect([$score->geoScore, $score->yearScore, $score->total()])->toBe([5000, 5000, 10000])
        ->and($score->distanceMeters)->toBe(0)
        ->and($score->yearDifference)->toBe(0);
});

it('decays exponentially with distance and years', function () {
    $paris = new Coordinates(48.8566, 2.3522);
    $score = $this->calculator->score($paris, 1946, $this->rome, 1921);

    // ~1106 km → 5000·e^(−1106/2000) ≈ 2876; 25 years → 5000·e^(−1) ≈ 1839.
    expect($score->geoScore)->toBeGreaterThan(2860)->toBeLessThan(2890)
        ->and($score->yearScore)->toBe(1839);
});

it('approaches zero for the worst guesses', function () {
    $antipode = new Coordinates(-41.895466, -167.523232);
    $score = $this->calculator->score($antipode, 2000, $this->rome, -3000);

    expect($score->geoScore)->toBeLessThan(20)
        ->and($score->yearScore)->toBe(0)
        ->and($score->total())->toBeGreaterThanOrEqual(0);
});

it('counts years across the BC/AD boundary without a year zero', function () {
    $score = $this->calculator->score($this->rome, 1, $this->rome, -1);

    expect($score->yearDifference)->toBe(1);
});

it('reads its constants from config', function () {
    config(['game.scoring.max_points' => 1000, 'game.scoring.year_scale' => 10]);
    $rome = new Coordinates(41.9, 12.5);

    $score = app(ScoreCalculator::class)->score($rome, 1910, $rome, 1900);

    expect($score->geoScore)->toBe(1000)->and($score->yearScore)->toBe(368);
});
