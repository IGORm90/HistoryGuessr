<script setup lang="ts">
import { ref, watch } from 'vue';
import type { PaddingOptions } from 'maplibre-gl';
import { LngLatBounds, createMarker, markerElement } from '@/lib/maplibre';
import { useMap } from '@/composables/useMap';
import { toLngLat } from './coordinates';

/**
 * A still world map behind the home page hero, with «?» pins. The pins are decorative places,
 * never the questions of a quiz: those would give the answers away.
 */
const props = withDefaults(defineProps<{ padding?: Partial<PaddingOptions> }>(), { padding: () => ({}) });

const PINS = [
    { latitude: 40.71, longitude: -74.01 },
    { latitude: 48.86, longitude: 2.35 },
    { latitude: 55.75, longitude: 37.62 },
    { latitude: 30.04, longitude: 31.24 },
    { latitude: 35.68, longitude: 139.69 },
    { latitude: -22.91, longitude: -43.17 },
    { latitude: 28.61, longitude: 77.21 },
    { latitude: -33.87, longitude: 151.21 },
];

const container = ref<HTMLElement | null>(null);
const { map } = useMap(container, { interactive: false });

watch(map, (instance) => {
    if (!instance) {
        return;
    }
    for (const pin of PINS) {
        const element = markerElement('marker-numbered');
        element.textContent = '?';
        createMarker({ element }).setLngLat(toLngLat(pin)).addTo(instance);
    }
    instance.fitBounds(new LngLatBounds([-80, -40], [155, 60]), {
        padding: { top: 60, bottom: 60, left: 60, right: 60, ...props.padding },
        animate: false,
    });
});
</script>

<template>
    <div ref="container" class="map-fullscreen h-full w-full" aria-hidden="true" />
</template>
