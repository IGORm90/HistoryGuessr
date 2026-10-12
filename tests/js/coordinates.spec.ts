import { describe, expect, it } from 'vitest';
import { fromLngLat, shortestLine, toLngLat, wrapLongitude } from '@/components/maps/coordinates';

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
});
