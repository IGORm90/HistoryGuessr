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

    /*
    | Round timer: each question must be answered within `time_limit` seconds of being shown.
    | `grace` seconds are tolerated server-side for network latency and are never shown to the player.
    | The limit is snapshotted into the game session at start.
    */
    'round' => [
        'time_limit' => (int) env('GAME_ROUND_TIME_LIMIT', 90),
        'grace' => (int) env('GAME_ROUND_GRACE', 3),
    ],

    // How long public question data stays in the cache (seconds).
    'question_cache_ttl' => (int) env('GAME_QUESTION_CACHE_TTL', 3600),
];
