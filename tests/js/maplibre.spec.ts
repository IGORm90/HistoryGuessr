import { describe, expect, it } from 'vitest';
import { absoluteUrl, resolveStyle } from '@/lib/maplibre';
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
