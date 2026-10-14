import { describe, expect, it, vi } from 'vitest';
import { GRATICULE, absoluteUrl, addGraticule, resolveStyle, type MapLibreMap } from '@/lib/maplibre';
import { mapConfig } from '@/config/map';

describe('absoluteUrl', () => {
    it('resolves the style URLs against the page origin', () => {
        expect(absoluteUrl('/maps/tiles/osm/tiles.json', 'https://historyguessr.example')).toBe(
            'https://historyguessr.example/maps/tiles/osm/tiles.json',
        );
        expect(absoluteUrl('/maps/assets/glyphs/noto_sans_regular/0-255.pbf')).toBe(
            `${window.location.origin}/maps/assets/glyphs/noto_sans_regular/0-255.pbf`,
        );
    });

    it('keeps absolute URLs as they are', () => {
        expect(absoluteUrl('https://historyguessr.example/maps/tiles/osm/1/0/0', 'http://localhost:8080')).toBe(
            'https://historyguessr.example/maps/tiles/osm/1/0/0',
        );
    });
});

describe('resolveStyle', () => {
    it('makes glyph, sprite and source URLs absolute and keeps their placeholders', () => {
        const resolved = resolveStyle(mapConfig.style, 'https://historyguessr.example');

        expect(resolved.glyphs).toBe('https://historyguessr.example/maps/assets/glyphs/{fontstack}/{range}.pbf');
        expect(resolved.sprite).toEqual([{ id: 'base', url: 'https://historyguessr.example/maps/assets/sprites/base' }]);
        expect(Object.values(resolved.sources)).toEqual([
            expect.objectContaining({ url: 'https://historyguessr.example/maps/tiles/osm/tiles.json' }),
        ]);
        expect(resolved.layers).toBe(mapConfig.style.layers);
    });
});

describe('addGraticule', () => {
    function fakeMap() {
        const sources: Record<string, unknown> = {};
        return {
            getSource: vi.fn((id: string) => sources[id]),
            getStyle: () => ({ layers: [{ id: 'water', type: 'fill' }, { id: 'label-city', type: 'symbol' }] }),
            addSource: vi.fn((id: string, source: unknown) => (sources[id] = source)),
            addLayer: vi.fn(),
        };
    }

    it('draws the coordinate grid under the first label layer, once', () => {
        const map = fakeMap();

        addGraticule(map as unknown as MapLibreMap);
        addGraticule(map as unknown as MapLibreMap);

        expect(map.addSource).toHaveBeenCalledTimes(1);
        expect(map.addSource).toHaveBeenCalledWith(GRATICULE, expect.objectContaining({ type: 'geojson' }));
        expect(map.addLayer).toHaveBeenCalledWith(expect.objectContaining({ id: GRATICULE, type: 'line' }), 'label-city');
    });
});
