<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import type { PaddingOptions } from 'maplibre-gl';
import {
    LngLatBounds,
    createMarker,
    markerElement,
    type GeoJSONSource,
    type MapLibreMap,
    type MapMouseEvent,
    type Marker,
} from '@/lib/maplibre';
import { useMap } from '@/composables/useMap';
import { mapConfig } from '@/config/map';
import type { Coordinates } from '@/types/game';
import type { Feature, FeatureCollection, LineString } from 'geojson';
import { formatCoordinates, formatDistance } from '@/utils/format';
import { fromLngLat, greatCircle, toLngLat, type LngLatTuple } from './coordinates';
import MapError from './MapError.vue';

/** The answer shown on the map; `guess` is null when the round timed out without a point. */
export type MapReveal = {
    guess: Coordinates | null;
    correct: Coordinates;
    distanceMeters: number | null;
    locationName: string;
};

/**
 * The full-screen game map. While guessing, a click places the player's point (dragged to move it);
 * with `reveal` the same map instance shows both points, the geodesic between them and fits the view.
 * `padding` keeps the points clear of the panels laid over the map.
 */
const props = withDefaults(defineProps<{ disabled?: boolean; reveal?: MapReveal | null; padding?: Partial<PaddingOptions> }>(), {
    disabled: false,
    reveal: null,
    padding: () => ({}),
});
const model = defineModel<Coordinates | null>({ default: null });

const LINE_SOURCE = 'answer-line';
// Region scale, not street scale (MapLibre levels, see config/map.ts).
const MAX_FIT_ZOOM = 7;
const TIMEOUT_ZOOM = 5;
const MOVE_MS = 900;
const LINE_DRAW_MS = 600;

const container = ref<HTMLElement | null>(null);
const { map, ready, error, retry } = useMap(container);

const locked = computed(() => props.disabled || props.reveal !== null);

function animated(): boolean {
    return typeof window.matchMedia === 'function' && !window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function caption(className: string, text: string): HTMLElement {
    const element = markerElement(className);
    element.textContent = text;
    return element;
}

/* ───── guessing: the player's point ───── */

let pick: { marker: Marker; label: Marker } | null = null;

function pickLabel(point: Coordinates): string {
    return `Ваша отметка · ${formatCoordinates(point)}`;
}

function removePick(): void {
    pick?.marker.remove();
    pick?.label.remove();
    pick = null;
}

function syncPick(): void {
    const instance = map.value;
    const point = props.reveal ? null : model.value;
    if (!instance || !point) {
        removePick();
        return;
    }
    if (pick) {
        pick.marker.setLngLat(toLngLat(point));
        pick.label.setLngLat(toLngLat(point));
        pick.label.getElement().textContent = pickLabel(point);
        return;
    }
    const marker = createMarker({ element: markerElement('marker-guess', 'Ваша отметка'), draggable: !locked.value })
        .setLngLat(toLngLat(point))
        .addTo(instance);
    const label = createMarker({ element: caption('map-label', pickLabel(point)), anchor: 'left', offset: [22, -14] })
        .setLngLat(toLngLat(point))
        .addTo(instance);
    marker.on('drag', () => {
        label.setLngLat(marker.getLngLat());
        label.getElement().textContent = pickLabel(fromLngLat(marker.getLngLat()));
    });
    marker.on('dragend', () => {
        if (!locked.value) {
            model.value = fromLngLat(marker.getLngLat());
        }
    });
    pick = { marker, label };
}

/* ───── reveal: both points and the line between them ───── */

let answer: { map: MapLibreMap; markers: Marker[]; key: string } | null = null;
let lineFrame = 0;

const NO_LINE: FeatureCollection<LineString> = { type: 'FeatureCollection', features: [] };

function line(coordinates: LngLatTuple[]): Feature<LineString> {
    return { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates } };
}

function setLine(instance: MapLibreMap, data: Feature<LineString> | FeatureCollection<LineString>): void {
    const source = instance.getSource<GeoJSONSource>(LINE_SOURCE);
    if (source) {
        source.setData(data);
        return;
    }
    instance.addSource(LINE_SOURCE, { type: 'geojson', data });
    instance.addLayer({
        id: LINE_SOURCE,
        type: 'line',
        source: LINE_SOURCE,
        layout: { 'line-cap': 'butt' },
        paint: { 'line-color': '#1d2b2a', 'line-width': 3, 'line-dasharray': [2, 1.5] },
    });
}

/** The dashed line «draws itself» from the guess to the correct place. */
function drawLine(instance: MapLibreMap, points: LngLatTuple[]): void {
    stopLine();
    if (!animated() || typeof requestAnimationFrame !== 'function') {
        setLine(instance, line(points));
        return;
    }
    const started = performance.now();
    const step = (now: number): void => {
        const progress = Math.min(1, (now - started) / LINE_DRAW_MS);
        setLine(instance, line(points.slice(0, Math.max(2, Math.ceil(points.length * progress)))));
        lineFrame = progress < 1 ? requestAnimationFrame(step) : 0;
    };
    setLine(instance, line(points.slice(0, 2)));
    lineFrame = requestAnimationFrame(step);
}

function stopLine(): void {
    if (lineFrame) {
        cancelAnimationFrame(lineFrame);
        lineFrame = 0;
    }
}

function clearAnswer(): void {
    stopLine();
    answer?.markers.forEach((marker) => marker.remove());
    answer = null;
}

function revealKey(reveal: MapReveal): string {
    return JSON.stringify([reveal.guess, reveal.correct]);
}

function drawReveal(): void {
    const instance = map.value;
    const reveal = props.reveal;
    if (!instance || !ready.value) {
        return;
    }
    if (!reveal) {
        if (answer) {
            clearAnswer();
            setLine(instance, NO_LINE);
            const { center, zoom } = mapConfig.world;
            instance.flyTo({ center: toLngLat(center), zoom, padding: { top: 0, right: 0, bottom: 0, left: 0 }, duration: animated() ? MOVE_MS : 0 });
        }
        return;
    }
    const key = revealKey(reveal);
    if (answer?.map === instance && answer.key === key) {
        return;
    }
    clearAnswer();

    const points = reveal.guess ? greatCircle(reveal.guess, reveal.correct) : [toLngLat(reveal.correct)];
    const from = points[0]!;
    const to = points[points.length - 1]!;
    const markers = [
        createMarker({ element: markerElement('marker-correct', 'Место съёмки') }).setLngLat(to).addTo(instance),
        createMarker({
            element: caption('map-label map-label--stamp', `${reveal.locationName} · ${formatCoordinates(reveal.correct)}`),
            anchor: 'top',
            offset: [0, 24],
        })
            .setLngLat(to)
            .addTo(instance),
    ];
    const duration = animated() ? MOVE_MS : 0;

    if (reveal.guess) {
        drawLine(instance, points);
        markers.push(createMarker({ element: markerElement('marker-guess marker-small', 'Ваша отметка') }).setLngLat(from).addTo(instance));
        if (reveal.distanceMeters !== null) {
            markers.push(
                createMarker({ element: caption('map-label map-label--distance', formatDistance(reveal.distanceMeters)), anchor: 'bottom', offset: [0, -8] })
                    .setLngLat(points[Math.floor(points.length / 2)]!)
                    .addTo(instance),
            );
        }
        const longitudes = points.map(([longitude]) => longitude);
        const latitudes = points.map(([, latitude]) => latitude);
        instance.fitBounds(
            new LngLatBounds([Math.min(...longitudes), Math.min(...latitudes)], [Math.max(...longitudes), Math.max(...latitudes)]),
            { padding: fullPadding(), maxZoom: MAX_FIT_ZOOM, duration },
        );
    } else {
        setLine(instance, NO_LINE);
        instance.flyTo({ center: to, zoom: TIMEOUT_ZOOM, padding: fullPadding(), duration });
    }
    answer = { map: instance, markers, key };
}

function fullPadding(): PaddingOptions {
    return { top: 80, right: 80, bottom: 80, left: 80, ...props.padding };
}

watch(map, (instance) => {
    // A new map (after a retry) starts without the old markers.
    pick = null;
    answer = null;
    if (!instance) {
        return;
    }
    instance.on('click', (event: MapMouseEvent) => {
        if (!locked.value) {
            model.value = fromLngLat(event.lngLat);
        }
    });
    syncPick();
});

watch(model, syncPick);
watch(locked, (value) => pick?.marker.setDraggable(!value));
watch(ready, drawReveal);
watch(
    () => props.reveal,
    () => {
        syncPick();
        drawReveal();
    },
    { deep: true },
);

onBeforeUnmount(stopLine);
</script>

<template>
    <div class="relative h-full w-full">
        <div
            ref="container"
            class="h-full w-full"
            :role="reveal ? 'img' : 'application'"
            :aria-label="
                reveal
                    ? reveal.guess
                        ? 'Карта с вашим и правильным ответом'
                        : 'Карта с правильным ответом'
                    : 'Карта: нажмите, чтобы поставить отметку'
            "
        />
        <MapError v-if="error" @retry="retry" />
    </div>
</template>
