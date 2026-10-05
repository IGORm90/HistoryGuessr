import type { Map as LeafletMap, Marker, LeafletMouseEvent } from 'leaflet';
import { L, createTileLayer } from '@/lib/leaflet';

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

const DEFAULT_CENTER: [number, number] = [30, 10];
const DEFAULT_ZOOM = 2;
const SELECTED_ZOOM = 6;

function toNumber(value: unknown): number | null {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    const number = typeof value === 'number' ? value : Number.parseFloat(String(value));
    return Number.isFinite(number) ? number : null;
}

function round(value: number): number {
    return Math.round(value * 1e6) / 1e6;
}

/**
 * Alpine component behind the Filament LeafletLocationPicker field.
 * `latitude` / `longitude` are entangled with the sibling numeric inputs, so both stay in sync.
 */
function leafletLocationPicker(config: PickerConfig) {
    let map: LeafletMap | null = null;
    let marker: Marker | null = null;
    let resizeObserver: ResizeObserver | null = null;

    return {
        ...config,

        init(this: PickerComponent) {
            map = L.map(this.$refs.map, { worldCopyJump: true }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);
            createTileLayer().addTo(map);

            const place = (lat: number, lng: number, pan: boolean) => {
                if (!map) {
                    return;
                }
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], { draggable: !this.disabled }).addTo(map);
                    marker.on('dragend', () => {
                        const position = marker!.getLatLng().wrap();
                        this.latitude = round(position.lat);
                        this.longitude = round(position.lng);
                    });
                }
                if (pan) {
                    map.setView([lat, lng], Math.max(map.getZoom(), SELECTED_ZOOM));
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

            map.on('click', (event: LeafletMouseEvent) => {
                if (this.disabled) {
                    return;
                }
                const position = event.latlng.wrap();
                this.latitude = round(position.lat);
                this.longitude = round(position.lng);
            });

            this.$watch('latitude', () => syncFromState(false));
            this.$watch('longitude', () => syncFromState(false));

            // The map may be rendered inside a collapsed section or a modal.
            resizeObserver = new ResizeObserver(() => map?.invalidateSize());
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
    (window as unknown as { Alpine: AlpineGlobal }).Alpine.data('leafletLocationPicker', leafletLocationPicker);
});
