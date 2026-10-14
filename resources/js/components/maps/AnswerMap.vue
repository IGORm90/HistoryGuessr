<script setup lang="ts">
import { ref, watch } from 'vue';
import {
    LngLatBounds,
    createMarker,
    markerElement,
    type GeoJSONSource,
    type MapLibreMap,
    type Marker,
} from '@/lib/maplibre';
import { useMap } from '@/composables/useMap';
import type { Coordinates } from '@/types/game';
import type { Feature, FeatureCollection, LineString } from 'geojson';
import { shortestLine, toLngLat, type LngLatTuple } from './coordinates';
import MapError from './MapError.vue';

// `guess` is null when the round timed out: only the correct place is shown.
const props = defineProps<{ guess: Coordinates | null; correct: Coordinates }>();

const LINE_SOURCE = 'answer-line';
// Region scale, not street scale (MapLibre levels, see config/map.ts).
const MAX_FIT_ZOOM = 7;

const container = ref<HTMLElement | null>(null);
const { map, ready, error, retry } = useMap(container, { scrollZoom: false });
let markers: { map: MapLibreMap; guess: Marker | null; correct: Marker } | null = null;

function line(from: LngLatTuple, to: LngLatTuple): Feature<LineString> {
    return { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [from, to] } };
}

const NO_LINE: FeatureCollection<LineString> = { type: 'FeatureCollection', features: [] };

function draw(): void {
    const instance = map.value;
    if (!instance || !ready.value) {
        return;
    }
    const [from, to] = props.guess ? shortestLine(props.guess, props.correct) : [null, toLngLat(props.correct)];
    const data = from ? line(from, to) : NO_LINE;

    const source = instance.getSource<GeoJSONSource>(LINE_SOURCE);
    if (source) {
        source.setData(data);
    } else {
        instance.addSource(LINE_SOURCE, { type: 'geojson', data });
        instance.addLayer({
            id: LINE_SOURCE,
            type: 'line',
            source: LINE_SOURCE,
            layout: { 'line-cap': 'round' },
            paint: { 'line-color': '#3b2a1e', 'line-width': 2, 'line-dasharray': [3, 3] },
        });
    }

    if (markers?.map !== instance) {
        markers = {
            map: instance,
            guess: null,
            correct: createMarker({ element: markerElement('marker-correct', 'Правильный ответ') }).setLngLat(to).addTo(instance),
        };
    } else {
        markers.correct.setLngLat(to);
    }

    if (from) {
        markers.guess ??= createMarker({ element: markerElement('marker-guess marker-small', 'Ваш ответ') }).addTo(instance);
        markers.guess.setLngLat(from);
        instance.fitBounds(new LngLatBounds(from, to), { padding: 40, maxZoom: MAX_FIT_ZOOM, animate: false });
    } else {
        markers.guess?.remove();
        markers.guess = null;
        instance.jumpTo({ center: to, zoom: MAX_FIT_ZOOM });
    }
}

watch(ready, draw);
watch(() => [props.guess, props.correct], draw, { deep: true });
</script>

<template>
    <div class="relative h-full w-full">
        <div
            ref="container"
            class="h-full w-full"
            role="img"
            :aria-label="guess ? 'Карта с вашим и правильным ответом' : 'Карта с правильным ответом'"
        />
        <MapError v-if="error" @retry="retry" />
    </div>
</template>
