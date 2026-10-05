<?php

namespace App\Services;

use App\DTO\Game\Coordinates;

/**
 * Great-circle distance via the Haversine formula on a spherical Earth.
 */
class DistanceCalculator
{
    /** IUGG mean Earth radius. */
    public const float EARTH_RADIUS_METERS = 6_371_008.8;

    public function meters(Coordinates $from, Coordinates $to): int
    {
        $lat1 = deg2rad($from->latitude);
        $lat2 = deg2rad($to->latitude);
        $deltaLat = $lat2 - $lat1;
        $deltaLng = deg2rad($to->longitude - $from->longitude);

        $a = sin($deltaLat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($deltaLng / 2) ** 2;
        // Clamp guards against floating-point drift slightly above 1 for antipodal points.
        $c = 2 * asin(min(1.0, sqrt($a)));

        return (int) round(self::EARTH_RADIUS_METERS * $c);
    }
}
