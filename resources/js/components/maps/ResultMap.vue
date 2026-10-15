<script setup lang="ts">
import { onBeforeUnmount, ref, watch } from 'vue';
import type { PaddingOptions } from 'maplibre-gl';
import { LngLatBounds, createMarker, createPopup, markerElement, type Marker } from '@/lib/maplibre';
import { useMap } from '@/composables/useMap';
import type { Coordinates } from '@/types/game';
import { toLngLat } from './coordinates';
import MapError from './MapError.vue';

export type ResultPin = Coordinates & { position: number; timedOut: boolean; title: string; imageUrl: string | null };

/**
 * The result map: the correct place of every round as a numbered pin (grey for a round that ran out
 * of time), fitted into the part of the screen left free by the panels. `highlighted` enlarges one pin
 * and opens a popup with its photo; hovering (or tapping) a pin reports it through `hover`.
 */
const props = withDefaults(defineProps<{ pins: ResultPin[]; highlighted?: number | null; padding?: Partial<PaddingOptions> }>(), {
    highlighted: null,
    padding: () => ({}),
});

const emit = defineEmits<{ hover: [position: number | null] }>();

const MAX_FIT_ZOOM = 5;

const container = ref<HTMLElement | null>(null);
const { map, error, retry } = useMap(container);
const markers = new Map<number, Marker>();
const popup = createPopup({ closeButton: false, closeOnClick: false, offset: 24, maxWidth: '280px', className: 'result-popup' });

/** The popup body is built from DOM nodes: the title is plain text and is never parsed as HTML. */
function popupContent(pin: ResultPin): HTMLElement {
    const root = document.createElement('figure');
    if (pin.imageUrl) {
        const image = document.createElement('img');
        image.src = pin.imageUrl;
        image.alt = pin.title;
        image.loading = 'eager';
        root.append(image);
    }
    const caption = document.createElement('figcaption');
    caption.textContent = `${pin.position}. ${pin.title}`;
    root.append(caption);
    return root;
}

function showPopup(position: number | null): void {
    const instance = map.value;
    const pin = props.pins.find((candidate) => candidate.position === position);
    if (!instance || !pin) {
        popup.remove();
        return;
    }
    popup.setLngLat(toLngLat(pin)).setDOMContent(popupContent(pin)).addTo(instance);
}

function draw(): void {
    const instance = map.value;
    markers.forEach((marker) => marker.remove());
    markers.clear();
    popup.remove();
    if (!instance || props.pins.length === 0) {
        return;
    }
    for (const pin of props.pins) {
        // MapLibre positions the outer element with `transform`; the pin is drawn by an inner one, so its
        // `scale` (the active state, the appear animation) never shifts the pin away from its place.
        const element = document.createElement('div');
        element.className = 'result-pin';
        const pinElement = markerElement(`marker-numbered${pin.timedOut ? ' is-timeout' : ''}`, `${pin.position}. ${pin.title}`);
        pinElement.textContent = String(pin.position);
        element.append(pinElement);
        element.addEventListener('mouseenter', () => emit('hover', pin.position));
        element.addEventListener('mouseleave', () => emit('hover', null));
        // Touch screens have no hover: a tap toggles the pin.
        element.addEventListener('click', () => emit('hover', props.highlighted === pin.position ? null : pin.position));
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
    (current) => {
        markers.forEach((marker, position) => {
            const element = marker.getElement();
            element.classList.toggle('is-active', position === current);
            element.firstElementChild?.classList.toggle('is-active', position === current);
        });
        showPopup(current);
    },
);
onBeforeUnmount(() => popup.remove());
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
