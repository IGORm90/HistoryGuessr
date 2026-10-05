<?php

namespace App\Support;

use Random\Randomizer;

/**
 * Weighted random sampling without replacement (Efraimidis–Spirakis):
 * each item gets key = u^(1/weight) for uniform u ∈ (0, 1); the largest keys win.
 */
class WeightedSampler
{
    public function __construct(private readonly Randomizer $randomizer = new Randomizer) {}

    /**
     * @template TKey of array-key
     *
     * @param  array<TKey, int>  $weights  Item key => positive weight.
     * @return list<TKey> Selected keys, shuffled so heavier items are not biased towards the front.
     */
    public function sample(array $weights, int $count): array
    {
        $keys = [];
        foreach ($weights as $item => $weight) {
            $u = $this->randomizer->nextFloat() ?: PHP_FLOAT_MIN;
            $keys[$item] = $u ** (1 / max(1, $weight));
        }

        arsort($keys);

        return $this->randomizer->shuffleArray(array_slice(array_keys($keys), 0, max(0, $count)));
    }
}
