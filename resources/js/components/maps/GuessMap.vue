<script setup lang="ts">
import { ref, watch } from 'vue';
import { createMarker, markerElement, type MapMouseEvent, type Marker } from '@/lib/maplibre';
import { useMap } from '@/composables/useMap';
import type { Coordinates } from '@/types/game';
import { fromLngLat, toLngLat } from './coordinates';
import MapError from './MapError.vue';

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false });
const model = defineModel<Coordinates | null>({ default: null });

const container = ref<HTMLElement | null>(null);
const { map, error, retry } = useMap(container);
let marker: Marker | null = null;

function syncMarker(point: Coordinates | null): void {
    if (!map.value) {
        return;
    }
    if (!point) {
        marker?.remove();
        marker = null;
        return;
    }
    if (marker) {
        marker.setLngLat(toLngLat(point));
        return;
    }
    marker = createMarker({ element: markerElement('marker-guess'), draggable: !props.disabled })
        .setLngLat(toLngLat(point))
        .addTo(map.value);
    marker.on('dragend', () => {
        if (marker && !props.disabled) {
            model.value = fromLngLat(marker.getLngLat());
        }
    });
}

watch(map, (instance) => {
    // A new map (after a retry) starts without the old marker.
    marker = null;
    if (!instance) {
        return;
    }
    instance.on('click', (event: MapMouseEvent) => {
        if (!props.disabled) {
            model.value = fromLngLat(event.lngLat);
        }
    });
    syncMarker(model.value);
});

watch(model, (point) => syncMarker(point));

watch(
    () => props.disabled,
    (disabled) => marker?.setDraggable(!disabled),
);
</script>

<template>
    <div class="relative h-full w-full">
        <div
            ref="container"
            class="h-full w-full"
            role="application"
            aria-label="Карта: нажмите, чтобы поставить отметку"
        />
        <MapError v-if="error" @retry="retry" />
    </div>
</template>
