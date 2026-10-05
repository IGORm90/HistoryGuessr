<?php

return [
    /*
    | Scoring: points = round(max_points × exp(−error / scale)) for distance (km) and years.
    | Results are stored at answer time, so changing these never rewrites past scores.
    */
    'scoring' => [
        'max_points' => (int) env('GAME_MAX_POINTS', 5000),
        'geo_scale_km' => (float) env('GAME_GEO_SCALE_KM', 2000),
        'year_scale' => (float) env('GAME_YEAR_SCALE', 25),
    ],

    // How long public question data stays in the cache (seconds).
    'question_cache_ttl' => (int) env('GAME_QUESTION_CACHE_TTL', 3600),
];
