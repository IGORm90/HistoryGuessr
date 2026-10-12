import { onBeforeUnmount, onMounted, ref, shallowRef, type Ref } from 'vue';
import type { MapOptions } from 'maplibre-gl';
import { createMap, type MapLibreMap } from '@/lib/maplibre';

/**
 * Owns a MapLibre map bound to a container element: created on mount, removed on unmount,
 * resized whenever the container changes size (layout switches, full-screen sheet on mobile).
 * `ready` turns true once the style has loaded; `error` is set when the map cannot be shown
 * (no WebGL, style or TileJSON unavailable) and `retry()` builds it again.
 */
export function useMap(container: Ref<HTMLElement | null>, options: Partial<MapOptions> = {}) {
    const map = shallowRef<MapLibreMap | null>(null);
    const ready = ref(false);
    const error = ref(false);
    let resizeObserver: ResizeObserver | null = null;

    function destroy(): void {
        map.value?.remove();
        map.value = null;
        ready.value = false;
    }

    function create(): void {
        if (!container.value) {
            return;
        }
        error.value = false;
        let instance: MapLibreMap;
        try {
            instance = createMap(container.value, options);
        } catch (exception) {
            console.error('[map] cannot create the map', exception);
            error.value = true;
            return;
        }
        instance.on('load', () => {
            ready.value = true;
        });
        instance.on('error', (event) => {
            console.error('[map]', event.error);
            // A single missing tile later on is not fatal; failing before the first render is.
            if (!ready.value) {
                error.value = true;
            }
        });
        map.value = instance;
    }

    function retry(): void {
        destroy();
        create();
    }

    onMounted(() => {
        create();
        if (container.value && typeof ResizeObserver !== 'undefined') {
            resizeObserver = new ResizeObserver(() => map.value?.resize());
            resizeObserver.observe(container.value);
        }
    });

    onBeforeUnmount(() => {
        resizeObserver?.disconnect();
        destroy();
    });

    return { map, ready, error, retry };
}
