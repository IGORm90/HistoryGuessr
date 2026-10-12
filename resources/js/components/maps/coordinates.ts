import type { Coordinates } from '@/types/game';

/**
 * The only place where named coordinates become MapLibre's [lng, lat] tuples and back.
 */
export type LngLatTuple = [number, number];

const PRECISION = 1e6;

function round(value: number): number {
    return Math.round(value * PRECISION) / PRECISION;
}

/** Wraps any longitude into [-180, 180]. */
export function wrapLongitude(longitude: number): number {
    const wrapped = ((((longitude + 180) % 360) + 360) % 360) - 180;
    return wrapped === -180 && longitude > 0 ? 180 : wrapped;
}

export function toLngLat(point: Coordinates): LngLatTuple {
    return [point.longitude, point.latitude];
}

/** Converts a map click position (possibly on a repeated world copy) into valid coordinates. */
export function fromLngLat(lngLat: { lng: number; lat: number }): Coordinates {
    return {
        latitude: round(Math.max(-90, Math.min(90, lngLat.lat))),
        longitude: round(wrapLongitude(lngLat.lng)),
    };
}

/**
 * For drawing a line between two points: shifts `to` by ±360° so the line takes the short way
 * across the antimeridian instead of crossing the whole map.
 */
export function shortestLine(from: Coordinates, to: Coordinates): [LngLatTuple, LngLatTuple] {
    let longitude = to.longitude;
    if (longitude - from.longitude > 180) {
        longitude -= 360;
    } else if (from.longitude - longitude > 180) {
        longitude += 360;
    }
    return [toLngLat(from), [longitude, to.latitude]];
}
