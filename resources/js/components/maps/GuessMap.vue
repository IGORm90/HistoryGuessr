<script setup lang="ts">
import { ref, watch } from 'vue';
import type { LeafletMouseEvent, Marker } from 'leaflet';
import { L } from '@/lib/leaflet';
import { useLeafletMap } from '@/composables/useLeafletMap';
import type { Coordinates } from '@/types/game';
import { fromLatLng, toLatLng } from './coordinates';

const props = withDefaults(defineProps<{ disabled?: boolean }>(), { disabled: false });
const model = defineModel<Coordinates | null>({ default: null });

const container = ref<HTMLElement | null>(null);
const { map } = useLeafletMap(container);
let marker: Marker | null = null;

const guessIcon = L.divIcon({ className: 'marker-guess', iconSize: [20, 20], iconAnchor: [10, 10] });

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
        marker.setLatLng(toLatLng(point));
        return;
    }
    marker = L.marker(toLatLng(point), { icon: guessIcon, draggable: !props.disabled, keyboard: false }).addTo(map.value);
    marker.on('dragend', () => {
        if (marker && !props.disabled) {
            model.value = fromLatLng(marker.getLatLng());
        }
    });
}

watch(map, (instance) => {
    if (!instance) {
        return;
    }
    instance.on('click', (event: LeafletMouseEvent) => {
        if (!props.disabled) {
            model.value = fromLatLng(event.latlng);
        }
    });
    syncMarker(model.value);
});

watch(model, (point) => syncMarker(point));

watch(
    () => props.disabled,
    (disabled) => {
        if (marker?.dragging) {
            if (disabled) {
                marker.dragging.disable();
            } else {
                marker.dragging.enable();
            }
        }
    },
);
</script>

<template>
    <div
        ref="container"
        class="h-full w-full"
        role="application"
        aria-label="Карта: нажмите, чтобы поставить отметку"
    />
</template>
