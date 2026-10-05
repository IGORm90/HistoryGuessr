import type { Coordinates } from '@/types/game';

/**
 * The only place where named coordinates become Leaflet's [lat, lng] tuples and back.
 */
export type LatLngTuple = [number, number];

const PRECISION = 1e6;

function round(value: number): number {
    return Math.round(value * PRECISION) / PRECISION;
}

/** Wraps any longitude into [-180, 180]. */
export function wrapLongitude(longitude: number): number {
    const wrapped = ((((longitude + 180) % 360) + 360) % 360) - 180;
    return wrapped === -180 && longitude > 0 ? 180 : wrapped;
}

export function toLatLng(point: Coordinates): LatLngTuple {
    return [point.latitude, point.longitude];
}

/** Converts a Leaflet click position (possibly on a repeated world copy) into valid coordinates. */
export function fromLatLng(latlng: { lat: number; lng: number }): Coordinates {
    return {
        latitude: round(Math.max(-90, Math.min(90, latlng.lat))),
        longitude: round(wrapLongitude(latlng.lng)),
    };
}

/**
 * For drawing a line between two points: shifts `to` by ±360° so the line takes the short way
 * across the antimeridian instead of crossing the whole map.
 */
export function shortestLine(from: Coordinates, to: Coordinates): [LatLngTuple, LatLngTuple] {
    let longitude = to.longitude;
    if (longitude - from.longitude > 180) {
        longitude -= 360;
    } else if (from.longitude - longitude > 180) {
        longitude += 360;
    }
    return [toLatLng(from), [to.latitude, longitude]];
}
