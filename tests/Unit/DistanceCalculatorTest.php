<?php

use App\DTO\Game\Coordinates;
use App\Services\DistanceCalculator;

function km(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    return (new DistanceCalculator)->meters(new Coordinates($lat1, $lng1), new Coordinates($lat2, $lng2)) / 1000;
}

it('returns zero for identical points', function () {
    expect((new DistanceCalculator)->meters(new Coordinates(41.9, 12.5), new Coordinates(41.9, 12.5)))->toBe(0);
});

it('measures neighbouring cities', function () {
    // Moscow — Saint Petersburg ≈ 634 km.
    expect(km(55.7558, 37.6173, 59.9343, 30.3351))->toBeGreaterThan(630)->toBeLessThan(640);
});

it('measures antipodal points as half the circumference', function () {
    expect(km(0, 0, 0, 180))->toBeGreaterThan(20_014)->toBeLessThan(20_016)
        ->and(km(90, 0, -90, 0))->toBeGreaterThan(20_014)->toBeLessThan(20_016);
});

it('takes the short way across the antimeridian', function () {
    // Two points 2° apart across the date line, not 358° apart.
    expect(km(0, 179, 0, -179))->toBeGreaterThan(222)->toBeLessThan(223);
});

it('is symmetric', function () {
    expect(km(48.8584, 2.2945, 40.6892, -74.0445))->toBe(km(40.6892, -74.0445, 48.8584, 2.2945));
});
