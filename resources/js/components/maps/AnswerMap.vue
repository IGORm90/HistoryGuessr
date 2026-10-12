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
import type { Feature, LineString } from 'geojson';
import { shortestLine, type LngLatTuple } from './coordinates';
import MapError from './MapError.vue';

const props = defineProps<{ guess: Coordinates; correct: Coordinates }>();

const LINE_SOURCE = 'answer-line';
// Region scale, not street scale (MapLibre levels, see config/map.ts).
const MAX_FIT_ZOOM = 7;

const container = ref<HTMLElement | null>(null);
const { map, ready, error, retry } = useMap(container, { scrollZoom: false });
let markers: { map: MapLibreMap; guess: Marker; correct: Marker } | null = null;

function line(from: LngLatTuple, to: LngLatTuple): Feature<LineString> {
    return { type: 'Feature', properties: {}, geometry: { type: 'LineString', coordinates: [from, to] } };
}

function draw(): void {
    const instance = map.value;
    if (!instance || !ready.value) {
        return;
    }
    const [from, to] = shortestLine(props.guess, props.correct);

    const source = instance.getSource<GeoJSONSource>(LINE_SOURCE);
    if (source) {
        source.setData(line(from, to));
    } else {
        instance.addSource(LINE_SOURCE, { type: 'geojson', data: line(from, to) });
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
            guess: createMarker({ element: markerElement('marker-guess marker-small', 'Ваш ответ') }),
            correct: createMarker({ element: markerElement('marker-correct', 'Правильный ответ') }),
        };
        markers.guess.setLngLat(from).addTo(instance);
        markers.correct.setLngLat(to).addTo(instance);
    } else {
        markers.guess.setLngLat(from);
        markers.correct.setLngLat(to);
    }

    instance.fitBounds(new LngLatBounds(from, to), { padding: 40, maxZoom: MAX_FIT_ZOOM, animate: false });
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
            aria-label="Карта с вашим и правильным ответом"
        />
        <MapError v-if="error" @retry="retry" />
    </div>
</template>
