<script setup lang="ts">
import { ref, watch } from 'vue';
import type { LayerGroup } from 'leaflet';
import { L } from '@/lib/leaflet';
import { useLeafletMap } from '@/composables/useLeafletMap';
import type { Coordinates } from '@/types/game';
import { shortestLine } from './coordinates';

const props = defineProps<{ guess: Coordinates; correct: Coordinates }>();

const container = ref<HTMLElement | null>(null);
const { map } = useLeafletMap(container, { dragging: true, scrollWheelZoom: false });
let layers: LayerGroup | null = null;

const guessIcon = L.divIcon({ className: 'marker-guess', iconSize: [18, 18], iconAnchor: [9, 9] });
const correctIcon = L.divIcon({ className: 'marker-correct', iconSize: [22, 22], iconAnchor: [11, 11] });

function draw(): void {
    if (!map.value) {
        return;
    }
    layers?.remove();
    const [from, to] = shortestLine(props.guess, props.correct);
    layers = L.layerGroup([
        L.polyline([from, to], { color: '#1d2329', weight: 2, dashArray: '6 6', interactive: false }),
        L.marker(from, { icon: guessIcon, interactive: false, title: 'Ваш ответ' }),
        L.marker(to, { icon: correctIcon, interactive: false, title: 'Правильный ответ' }),
    ]).addTo(map.value);
    map.value.fitBounds(L.latLngBounds([from, to]), { padding: [40, 40], maxZoom: 8 });
}

watch(map, draw);
watch(() => [props.guess, props.correct], draw, { deep: true });
</script>

<template>
    <div
        ref="container"
        class="h-full w-full"
        role="img"
        aria-label="Карта с вашим и правильным ответом"
    />
</template>
