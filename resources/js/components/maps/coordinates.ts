import type { Coordinates } from '@/types/game';
import type { FeatureCollection, LineString } from 'geojson';

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

const RAD = Math.PI / 180;

/**
 * Points of the great circle (the geodesic) from `from` to `to`, starting exactly at `from`.
 * Longitudes are unwrapped so the line takes the short way across the antimeridian, like `shortestLine`.
 */
export function greatCircle(from: Coordinates, to: Coordinates, steps = 64): LngLatTuple[] {
    const [start, end] = shortestLine(from, to);
    const [lat1, lng1, lat2, lng2] = [from.latitude * RAD, from.longitude * RAD, to.latitude * RAD, to.longitude * RAD];
    const angle =
        2 *
        Math.asin(
            Math.sqrt(Math.sin((lat2 - lat1) / 2) ** 2 + Math.cos(lat1) * Math.cos(lat2) * Math.sin((lng2 - lng1) / 2) ** 2),
        );
    if (angle < 1e-9 || Math.abs(angle - Math.PI) < 1e-9) {
        return [start, end];
    }

    const points: LngLatTuple[] = [start];
    for (let index = 1; index < steps; index++) {
        const fraction = index / steps;
        const a = Math.sin((1 - fraction) * angle) / Math.sin(angle);
        const b = Math.sin(fraction * angle) / Math.sin(angle);
        const x = a * Math.cos(lat1) * Math.cos(lng1) + b * Math.cos(lat2) * Math.cos(lng2);
        const y = a * Math.cos(lat1) * Math.sin(lng1) + b * Math.cos(lat2) * Math.sin(lng2);
        const z = a * Math.sin(lat1) + b * Math.sin(lat2);
        let longitude = Math.atan2(y, x) / RAD;
        // Keep each step next to the previous one instead of jumping across the map.
        const previous = points[points.length - 1]![0];
        longitude += Math.round((previous - longitude) / 360) * 360;
        points.push([round(longitude), round(Math.atan2(z, Math.sqrt(x * x + y * y)) / RAD)]);
    }
    points.push(end);
    return points;
}

/** Mercator stops short of the poles; the grid ends there too. */
const GRID_LATITUDE = 85;

/** Parallels and meridians every `step` degrees: the coordinate grid of the atlas sheet. */
export function graticule(step = 15): FeatureCollection<LineString> {
    const lines: LngLatTuple[][] = [];
    for (let longitude = -180; longitude <= 180; longitude += step) {
        lines.push([
            [longitude, -GRID_LATITUDE],
            [longitude, GRID_LATITUDE],
        ]);
    }
    for (let latitude = -Math.floor(GRID_LATITUDE / step) * step; latitude <= GRID_LATITUDE; latitude += step) {
        lines.push([
            [-180, latitude],
            [180, latitude],
        ]);
    }
    return {
        type: 'FeatureCollection',
        features: lines.map((coordinates) => ({ type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates } })),
    };
}
