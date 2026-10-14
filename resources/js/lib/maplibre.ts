import {
    LngLatBounds,
    Map as MapLibreMap,
    Marker,
    NavigationControl,
    setWorkerUrl,
    type MapOptions,
    type MarkerOptions,
    type StyleSpecification,
} from 'maplibre-gl';
import 'maplibre-gl/dist/maplibre-gl.css';
// MapLibre locates its worker at runtime, which the bundler cannot see; ship it as an asset of our own.
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import { mapConfig } from '@/config/map';
import { graticule } from '@/components/maps/coordinates';

setWorkerUrl(workerUrl);

export type { GeoJSONSource, LngLat, MapMouseEvent } from 'maplibre-gl';
export { LngLatBounds, type MapLibreMap, type Marker };

/**
 * The style keeps same-origin URLs relative (/maps/...), but MapLibre needs absolute ones (tiles are
 * fetched in a web worker, where a relative URL does not resolve): they are resolved against the page.
 */
export function absoluteUrl(url: string, origin: string = window.location.origin): string {
    return new URL(url, origin).href;
}

/** The style with its glyph, sprite and source URLs made absolute. Placeholders like {z} survive. */
export function resolveStyle(style: StyleSpecification, origin: string = window.location.origin): StyleSpecification {
    const resolve = (url: string) => decodeURI(absoluteUrl(url, origin));
    const sources = Object.fromEntries(
        Object.entries(style.sources).map(([id, source]) => {
            const resolved = { ...source } as typeof source & { url?: string; tiles?: string[] };
            if (resolved.url) resolved.url = resolve(resolved.url);
            if (resolved.tiles) resolved.tiles = resolved.tiles.map(resolve);
            return [id, resolved];
        }),
    );
    return {
        ...style,
        glyphs: style.glyphs && resolve(style.glyphs),
        sprite: Array.isArray(style.sprite)
            ? style.sprite.map((sprite) => ({ ...sprite, url: resolve(sprite.url) }))
            : style.sprite && resolve(style.sprite),
        sources,
    };
}

/** A flat (mercator) map of the whole world with the shared style, zoom limits and controls. Throws without WebGL. */
export function createMap(container: HTMLElement, options: Partial<MapOptions> = {}): MapLibreMap {
    const { center, zoom } = mapConfig.world;
    const map = new MapLibreMap({
        container,
        style: resolveStyle(mapConfig.style),
        center: [center.longitude, center.latitude],
        zoom,
        minZoom: mapConfig.minZoom,
        maxZoom: mapConfig.maxZoom,
        renderWorldCopies: true,
        dragRotate: false,
        pitchWithRotate: false,
        touchPitch: false,
        attributionControl: { compact: false },
        transformRequest: (url: string) => ({ url: absoluteUrl(url) }),
        ...options,
    });
    map.touchZoomRotate.disableRotation();
    map.keyboard.disableRotation();
    if (options.interactive !== false) {
        map.addControl(new NavigationControl({ showCompass: false }), 'top-left');
    }
    map.on('load', () => addGraticule(map));
    return map;
}

export const GRATICULE = 'graticule';

/** The coordinate grid of the atlas sheet: over land and water, under the labels (and the HTML markers). */
export function addGraticule(map: MapLibreMap): void {
    if (map.getSource(GRATICULE)) {
        return;
    }
    const firstLabel = map.getStyle().layers.find((layer) => layer.type === 'symbol')?.id;
    map.addSource(GRATICULE, { type: 'geojson', data: graticule() });
    map.addLayer(
        { id: GRATICULE, type: 'line', source: GRATICULE, paint: { 'line-color': 'rgba(29,43,42,0.14)', 'line-width': 1 } },
        firstLabel,
    );
}

export function createMarker(options: MarkerOptions = {}): Marker {
    return new Marker(options);
}

/** A round marker drawn with CSS (`.marker-guess`, `.marker-correct` in app.css). */
export function markerElement(className: string, title?: string): HTMLElement {
    const element = document.createElement('div');
    element.className = className;
    if (title) {
        element.title = title;
        element.setAttribute('aria-label', title);
    }
    return element;
}
