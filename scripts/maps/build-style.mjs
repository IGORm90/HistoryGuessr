/**
 * Generates resources/js/config/map-style.json: the MapLibre style for the self-hosted map.
 * Run with `npm run maps:style` (or `make maps-style`) after bumping @versatiles/style; commit the result.
 *
 * Every resource URL is same-origin and relative (/maps/...): tiles via the TileJSON of the tile
 * server (its maxzoom follows the dataset), glyphs and sprites from docker/maps/assets.
 */
import { writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';
import { osm } from '@versatiles/style';

// The builder only emits absolute URLs; build against a placeholder origin and strip it.
const PLACEHOLDER = 'https://historyguessr.invalid';
const OUTPUT = fileURLToPath(new URL('../../resources/js/config/map-style.json', import.meta.url));

export const ATTRIBUTION = '© <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors';

export function buildStyle() {
    const style = osm({
        theme: 'natural',
        // Shift the palette towards the site's beige/brown (resources/css/app.css).
        recolor: { tint: { color: '#c99a3c', amount: 0.2 }, saturate: -0.15 },
        urls: {
            base: PLACEHOLDER,
            osm: '/maps/tiles/osm/tiles.json',
            glyphsPattern: '/maps/assets/glyphs/{fontstack}/{range}.pbf',
            sprite: [{ id: 'base', url: '/maps/assets/sprites/base' }],
        },
        // English name_en with a fallback to the local name; no transliteration.
        text: { language: 'en' },
        // Flat map: the game relies on world copies and the antimeridian handling of mercator.
        projection: 'mercator',
        sky: false,
    });

    const json = JSON.stringify(style).replaceAll(PLACEHOLDER, '');
    const result = JSON.parse(json);
    for (const source of Object.values(result.sources)) {
        source.attribution = ATTRIBUTION;
    }
    return result;
}

/** Resource URLs (tiles, TileJSON, glyphs, sprites) that MapLibre would request. */
export function resourceUrls(style) {
    const sprites = Array.isArray(style.sprite) ? style.sprite.map((sprite) => sprite.url) : [style.sprite];
    const sources = Object.values(style.sources).flatMap((source) => [source.url, ...(source.tiles ?? [])]);
    return [style.glyphs, ...sprites, ...sources].filter(Boolean);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
    const style = buildStyle();
    const foreign = resourceUrls(style).filter((url) => !url.startsWith('/maps/'));
    if (foreign.length > 0) {
        console.error(`Style requests resources outside /maps/: ${foreign.join(', ')}`);
        process.exit(1);
    }
    writeFileSync(OUTPUT, `${JSON.stringify(style, null, 1)}\n`);
    console.log(`Wrote ${OUTPUT} (${style.layers.length} layers)`);
}
