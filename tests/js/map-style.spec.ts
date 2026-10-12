import { describe, expect, it } from 'vitest';
import { mapConfig } from '@/config/map';
import style from '@/config/map-style.json';

// Map services the self-hosted map must never depend on at runtime.
const FORBIDDEN_HOSTS = ['maptiler', 'tile.openstreetmap.org', 'tiles.versatiles.org', 'api.mapbox.com', 'fonts.googleapis', 'unpkg.com'];

type Layer = { type: string; layout?: Record<string, unknown> };

function resourceUrls(): string[] {
    const sprites = Array.isArray(style.sprite) ? style.sprite.map((sprite) => sprite.url) : [style.sprite];
    const sources = Object.values(style.sources as Record<string, { url?: string; tiles?: string[] }>).flatMap(
        (source) => [source.url, ...(source.tiles ?? [])],
    );
    return [style.glyphs, ...sprites, ...sources].filter((url): url is string => typeof url === 'string');
}

describe('map style', () => {
    it('loads every resource same-origin from /maps/', () => {
        const urls = resourceUrls();
        expect(urls.length).toBeGreaterThanOrEqual(3);
        for (const url of urls) {
            expect(url).toMatch(/^\/maps\//);
        }
    });

    it('mentions no external map service or API key anywhere', () => {
        const json = JSON.stringify(style).toLowerCase();
        for (const host of FORBIDDEN_HOSTS) {
            expect(json).not.toContain(host);
        }
        expect(json).not.toMatch(/[?&](key|access_token|api_key)=/);
    });

    it('labels places in English with a fallback to the local name', () => {
        const labels = (style.layers as Layer[]).filter((layer) => layer.type === 'symbol' && layer.layout?.['text-field']);
        const places = labels.filter((layer) => JSON.stringify(layer.layout!['text-field']).includes('name'));

        expect(places.length).toBeGreaterThan(10);
        for (const layer of places) {
            expect(layer.layout!['text-field']).toEqual(['coalesce', ['get', 'name_en'], ['get', 'name']]);
        }
    });

    it('credits OpenStreetMap contributors with a link to the copyright page', () => {
        for (const source of Object.values(style.sources as Record<string, { attribution?: string }>)) {
            expect(source.attribution).toContain('OpenStreetMap');
            expect(source.attribution).toContain('https://www.openstreetmap.org/copyright');
        }
    });

    it('is a flat map limited to the zoom levels of the dataset', () => {
        expect(style.projection).toEqual({ type: 'mercator' });
        expect(mapConfig.maxZoom).toBe(12);
    });
});
