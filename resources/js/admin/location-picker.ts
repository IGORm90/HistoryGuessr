import { createMap, createMarker, type MapLibreMap, type MapMouseEvent, type Marker } from '@/lib/maplibre';
import { fromLngLat } from '@/components/maps/coordinates';

type PickerConfig = {
    latitude: unknown;
    longitude: unknown;
    disabled: boolean;
};

type PickerComponent = PickerConfig & {
    $refs: { map: HTMLElement };
    $watch: (property: string, callback: () => void) => void;
    init(): void;
    destroy(): void;
};

const SELECTED_ZOOM = 5;

function toNumber(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const number = typeof value === 'number' ? value : Number.parseFloat(String(value));
    return Number.isFinite(number) ? number : null;
}

/**
 * Alpine component behind the Filament MapLocationPicker field.
 * `latitude` / `longitude` are entangled with the sibling numeric inputs, so both stay in sync.
 */
function mapLocationPicker(config: PickerConfig) {
    let map: MapLibreMap | null = null;
    let marker: Marker | null = null;
    let resizeObserver: ResizeObserver | null = null;

    return {
        ...config,

        init(this: PickerComponent) {
            const instance = createMap(this.$refs.map);
            map = instance;

            const select = (position: { lng: number; lat: number }) => {
                const point = fromLngLat(position);
                this.latitude = point.latitude;
                this.longitude = point.longitude;
            };

            const place = (lat: number, lng: number, pan: boolean) => {
                if (marker) {
                    marker.setLngLat([lng, lat]);
                } else {
                    const created = createMarker({ draggable: !this.disabled, color: '#b3412a' })
                        .setLngLat([lng, lat])
                        .addTo(instance);
                    created.on('dragend', () => select(created.getLngLat()));
                    marker = created;
                }
                if (pan) {
                    instance.jumpTo({ center: [lng, lat], zoom: Math.max(instance.getZoom(), SELECTED_ZOOM) });
                }
            };

            const syncFromState = (pan: boolean) => {
                const lat = toNumber(this.latitude);
                const lng = toNumber(this.longitude);
                if (lat === null || lng === null || Math.abs(lat) > 90 || Math.abs(lng) > 180) {
                    return;
                }
                place(lat, lng, pan);
            };

            syncFromState(true);

            instance.on('click', (event: MapMouseEvent) => {
                if (!this.disabled) {
                    select(event.lngLat);
                }
            });

            this.$watch('latitude', () => syncFromState(false));
            this.$watch('longitude', () => syncFromState(false));

            // The map may be rendered inside a collapsed section or a modal.
            resizeObserver = new ResizeObserver(() => map?.resize());
            resizeObserver.observe(this.$refs.map);
        },

        destroy() {
            resizeObserver?.disconnect();
            map?.remove();
            map = null;
            marker = null;
        },
    };
}

type AlpineGlobal = { data: (name: string, factory: (config: PickerConfig) => object) => void };

document.addEventListener('alpine:init', () => {
    (window as unknown as { Alpine: AlpineGlobal }).Alpine.data('mapLocationPicker', mapLocationPicker);
});
