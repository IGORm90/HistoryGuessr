<script setup lang="ts">
import { ref, watch } from 'vue';
import type { PaddingOptions } from 'maplibre-gl';
import { LngLatBounds, createMarker, markerElement, type Marker } from '@/lib/maplibre';
import { useMap } from '@/composables/useMap';
import type { Coordinates } from '@/types/game';
import { toLngLat } from './coordinates';
import MapError from './MapError.vue';

export type ResultPin = Coordinates & { position: number; timedOut: boolean; title: string };

/**
 * The result map: the correct place of every round as a numbered pin (grey for a round that ran out
 * of time), fitted into the part of the screen left free by the panels. `highlighted` enlarges one pin.
 */
const props = withDefaults(defineProps<{ pins: ResultPin[]; highlighted?: number | null; padding?: Partial<PaddingOptions> }>(), {
    highlighted: null,
    padding: () => ({}),
});

const MAX_FIT_ZOOM = 5;

const container = ref<HTMLElement | null>(null);
const { map, error, retry } = useMap(container);
const markers = new Map<number, Marker>();

function draw(): void {
    const instance = map.value;
    markers.forEach((marker) => marker.remove());
    markers.clear();
    if (!instance || props.pins.length === 0) {
        return;
    }
    for (const pin of props.pins) {
        const element = markerElement(`marker-numbered${pin.timedOut ? ' is-timeout' : ''}`, `${pin.position}. ${pin.title}`);
        element.textContent = String(pin.position);
        markers.set(pin.position, createMarker({ element }).setLngLat(toLngLat(pin)).addTo(instance));
    }
    const longitudes = props.pins.map((pin) => pin.longitude);
    const latitudes = props.pins.map((pin) => pin.latitude);
    instance.fitBounds(new LngLatBounds([Math.min(...longitudes), Math.min(...latitudes)], [Math.max(...longitudes), Math.max(...latitudes)]), {
        padding: { top: 80, right: 80, bottom: 80, left: 80, ...props.padding },
        maxZoom: MAX_FIT_ZOOM,
        animate: false,
    });
}

watch(map, draw);
watch(() => props.pins, draw, { deep: true });
watch(
    () => props.highlighted,
    (current) => markers.forEach((marker, position) => marker.getElement().classList.toggle('is-active', position === current)),
);
</script>

<template>
    <div class="relative h-full w-full">
        <div
            ref="container"
            class="map-fullscreen h-full w-full"
            role="img"
            aria-label="Карта с местами съёмки всех раундов"
        />
        <MapError v-if="error" @retry="retry" />
    </div>
</template>
