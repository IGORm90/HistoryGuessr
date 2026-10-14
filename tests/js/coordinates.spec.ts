import { describe, expect, it } from 'vitest';
import { fromLngLat, graticule, greatCircle, shortestLine, toLngLat, wrapLongitude } from '@/components/maps/coordinates';

describe('map coordinates', () => {
    it('converts named coordinates to MapLibre tuples and back', () => {
        expect(toLngLat({ latitude: 41.9, longitude: 12.5 })).toEqual([12.5, 41.9]);
        expect(fromLngLat({ lat: 41.8954661234, lng: 12.4767689999 })).toEqual({ latitude: 41.895466, longitude: 12.476769 });
    });

    it('wraps clicks on repeated world copies into [-180, 180]', () => {
        expect(fromLngLat({ lat: 10, lng: 370 })).toEqual({ latitude: 10, longitude: 10 });
        expect(fromLngLat({ lat: 10, lng: -190 })).toEqual({ latitude: 10, longitude: 170 });
        expect(wrapLongitude(180)).toBe(180);
        expect(wrapLongitude(-180)).toBe(-180);
    });

    it('clamps latitude', () => {
        expect(fromLngLat({ lat: 95, lng: 0 }).latitude).toBe(90);
    });

    it('draws lines the short way across the antimeridian', () => {
        expect(shortestLine({ latitude: 0, longitude: 179 }, { latitude: 0, longitude: -179 })).toEqual([[179, 0], [181, 0]]);
        expect(shortestLine({ latitude: 0, longitude: -179 }, { latitude: 0, longitude: 179 })).toEqual([[-179, 0], [-181, 0]]);
        expect(shortestLine({ latitude: 0, longitude: 10 }, { latitude: 0, longitude: 20 })).toEqual([[10, 0], [20, 0]]);
    });

    it('follows the great circle between two points', () => {
        const line = greatCircle({ latitude: 40.7, longitude: -74 }, { latitude: 51.5, longitude: -0.1 });

        expect(line[0]).toEqual([-74, 40.7]);
        expect(line.at(-1)).toEqual([-0.1, 51.5]);
        // The geodesic between New York and London bends north of both ends.
        expect(Math.max(...line.map(([, latitude]) => latitude))).toBeGreaterThan(51.5);
    });

    it('keeps the great circle continuous across the antimeridian', () => {
        const line = greatCircle({ latitude: 35.7, longitude: 139.7 }, { latitude: 37.8, longitude: -122.4 });

        expect(line.at(-1)).toEqual([237.6, 37.8]);
        for (let index = 1; index < line.length; index++) {
            expect(Math.abs(line[index]![0] - line[index - 1]![0])).toBeLessThan(10);
        }
    });

    it('draws a single segment between identical points', () => {
        expect(greatCircle({ latitude: 1, longitude: 2 }, { latitude: 1, longitude: 2 })).toEqual([[2, 1], [2, 1]]);
    });

    it('builds a coordinate grid every 15 degrees', () => {
        const lines = graticule().features.map((feature) => feature.geometry.coordinates);

        expect(lines).toContainEqual([[-180, -85], [-180, 85]]);
        expect(lines).toContainEqual([[15, -85], [15, 85]]);
        expect(lines).toContainEqual([[-180, 75], [180, 75]]);
        expect(lines).toContainEqual([[-180, 0], [180, 0]]);
        expect(lines).toHaveLength(25 + 11);
    });
});
