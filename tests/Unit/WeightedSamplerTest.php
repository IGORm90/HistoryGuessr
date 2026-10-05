<?php

use App\Support\WeightedSampler;
use Random\Engine\Mt19937;
use Random\Randomizer;

function sampler(int $seed): WeightedSampler
{
    return new WeightedSampler(new Randomizer(new Mt19937($seed)));
}

it('returns exactly the requested number of distinct items', function () {
    $picked = sampler(1)->sample(array_fill_keys(range(1, 20), 1), 5);

    expect($picked)->toHaveCount(5)
        ->and(array_unique($picked))->toHaveCount(5)
        ->and(array_diff($picked, range(1, 20)))->toBe([]);
});

it('returns everything when asking for more than available', function () {
    $picked = sampler(2)->sample([10 => 1, 11 => 1, 12 => 1], 5);

    sort($picked);
    expect($picked)->toBe([10, 11, 12]);
});

it('is deterministic for a given seed', function () {
    $weights = array_fill_keys(range(1, 50), 1);

    expect(sampler(42)->sample($weights, 10))->toBe(sampler(42)->sample($weights, 10));
});

it('prefers heavier items', function () {
    $hits = ['heavy' => 0, 'light' => 0];
    $randomizer = new Randomizer(new Mt19937(7));
    $sampler = new WeightedSampler($randomizer);

    for ($i = 0; $i < 2000; $i++) {
        $hits[$sampler->sample(['heavy' => 3, 'light' => 1], 1)[0]]++;
    }

    // Expected share for weight 3 vs 1 is 75%.
    expect($hits['heavy'] / 2000)->toBeGreaterThan(0.72)->toBeLessThan(0.78);
});
