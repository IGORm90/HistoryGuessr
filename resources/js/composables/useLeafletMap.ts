import { onBeforeUnmount, onMounted, shallowRef, type Ref } from 'vue';
import type { Map as LeafletMap, MapOptions } from 'leaflet';
import { L, createTileLayer } from '@/lib/leaflet';

const WORLD_VIEW: { center: [number, number]; zoom: number } = { center: [30, 10], zoom: 2 };

/**
 * Owns a Leaflet map bound to a container element: created on mount, removed on unmount,
 * resized whenever the container changes size (layout switches, full-screen sheet on mobile).
 */
export function useLeafletMap(container: Ref<HTMLElement | null>, options: MapOptions = {}) {
    const map = shallowRef<LeafletMap | null>(null);
    let resizeObserver: ResizeObserver | null = null;

    onMounted(() => {
        if (!container.value) {
            return;
        }
        const instance = L.map(container.value, {
            worldCopyJump: true,
            minZoom: 1,
            // Keep the view on the map vertically so tall phone screens never show empty grey bands.
            maxBounds: L.latLngBounds([-85, -540], [85, 540]),
            maxBoundsViscosity: 1,
            ...options,
        });
        instance.setView(WORLD_VIEW.center, WORLD_VIEW.zoom);
        createTileLayer().addTo(instance);
        map.value = instance;

        if (typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => instance.invalidateSize());
            resizeObserver.observe(container.value);
        }
    });

    onBeforeUnmount(() => {
        resizeObserver?.disconnect();
        map.value?.remove();
        map.value = null;
    });

    return { map };
}
