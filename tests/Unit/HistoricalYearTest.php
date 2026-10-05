<?php

use App\Support\HistoricalYear;

it('rejects year zero and out-of-range values', function (int $year) {
    new HistoricalYear($year);
})->throws(InvalidArgumentException::class)->with([0, HistoricalYear::MIN - 1, HistoricalYear::MAX + 1]);

it('builds signed years from number and era', function () {
    expect(HistoricalYear::fromEra(44, beforeCommonEra: true)->value)->toBe(-44)
        ->and(HistoricalYear::fromEra(1921, beforeCommonEra: false)->value)->toBe(1921);
});

it('rejects non-positive absolute years in era form', function () {
    HistoricalYear::fromEra(0, beforeCommonEra: true);
})->throws(InvalidArgumentException::class);

it('calculates differences skipping the non-existent year zero', function (int $a, int $b, int $expected) {
    expect(HistoricalYear::difference($a, $b))->toBe($expected)
        ->and(HistoricalYear::difference($b, $a))->toBe($expected);
})->with([
    'same year' => [1921, 1921, 0],
    'both AD' => [1900, 1921, 21],
    'both BC' => [-490, -44, 446],
    '1 BC to AD 1' => [-1, 1, 1],
    '44 BC to AD 14' => [-44, 14, 57],
]);

it('formats years for display', function () {
    expect((new HistoricalYear(-44))->format())->toBe('44 г. до н. э.')
        ->and((string) new HistoricalYear(1921))->toBe('1921 г.');
});
