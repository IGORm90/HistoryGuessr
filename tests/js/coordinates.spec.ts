import { describe, expect, it } from 'vitest';
import { fromLatLng, shortestLine, toLatLng, wrapLongitude } from '@/components/maps/coordinates';

describe('map coordinates', () => {
    it('converts named coordinates to Leaflet tuples and back', () => {
        expect(toLatLng({ latitude: 41.9, longitude: 12.5 })).toEqual([41.9, 12.5]);
        expect(fromLatLng({ lat: 41.8954661234, lng: 12.4767689999 })).toEqual({ latitude: 41.895466, longitude: 12.476769 });
    });

    it('wraps clicks on repeated world copies into [-180, 180]', () => {
        expect(fromLatLng({ lat: 10, lng: 370 })).toEqual({ latitude: 10, longitude: 10 });
        expect(fromLatLng({ lat: 10, lng: -190 })).toEqual({ latitude: 10, longitude: 170 });
        expect(wrapLongitude(180)).toBe(180);
        expect(wrapLongitude(-180)).toBe(-180);
    });

    it('clamps latitude', () => {
        expect(fromLatLng({ lat: 95, lng: 0 }).latitude).toBe(90);
    });

    it('draws lines the short way across the antimeridian', () => {
        expect(shortestLine({ latitude: 0, longitude: 179 }, { latitude: 0, longitude: -179 })).toEqual([[0, 179], [0, 181]]);
        expect(shortestLine({ latitude: 0, longitude: -179 }, { latitude: 0, longitude: 179 })).toEqual([[0, -179], [0, -181]]);
        expect(shortestLine({ latitude: 0, longitude: 10 }, { latitude: 0, longitude: 20 })).toEqual([[0, 10], [0, 20]]);
    });
});
