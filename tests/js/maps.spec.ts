import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

type Handler = (event: { latlng: { lat: number; lng: number } }) => void;

const leaflet = vi.hoisted(() => {
    const handlers: Record<string, Handler> = {};
    const map = {
        setView: vi.fn().mockReturnThis(),
        on: vi.fn((name: string, handler: Handler) => {
            handlers[name] = handler;
            return map;
        }),
        remove: vi.fn(),
        invalidateSize: vi.fn(),
        fitBounds: vi.fn(),
    };
    const markerInstance = () => {
        const instance = {
            addTo: vi.fn().mockReturnThis(),
            setLatLng: vi.fn().mockReturnThis(),
            on: vi.fn().mockReturnThis(),
            remove: vi.fn(),
            getLatLng: vi.fn(),
            dragging: { enable: vi.fn(), disable: vi.fn() },
        };
        return instance;
    };
    const L = {
        map: vi.fn(() => map),
        marker: vi.fn(markerInstance),
        divIcon: vi.fn(() => ({})),
        polyline: vi.fn(() => ({})),
        layerGroup: vi.fn(() => ({ addTo: vi.fn().mockReturnThis(), remove: vi.fn() })),
        latLngBounds: vi.fn((...points: unknown[]) => ({ points })),
        tileLayer: vi.fn(() => ({ addTo: vi.fn() })),
    };
    return { L, map, handlers };
});

vi.mock('@/lib/leaflet', () => ({
    L: leaflet.L,
    createTileLayer: () => ({ addTo: vi.fn() }),
}));

import GuessMap from '@/components/maps/GuessMap.vue';
import AnswerMap from '@/components/maps/AnswerMap.vue';

describe('GuessMap', () => {
    beforeEach(() => vi.clearAllMocks());

    it('turns a click into named coordinates', async () => {
        const wrapper = mount(GuessMap, { props: { modelValue: null } });
        await flushPromises();

        leaflet.handlers.click!({ latlng: { lat: 41.8954661, lng: 372.4767689 } });

        expect(wrapper.emitted('update:modelValue')![0]).toEqual([{ latitude: 41.895466, longitude: 12.476769 }]);
    });

    it('places a marker for the selected point', async () => {
        const wrapper = mount(GuessMap, { props: { modelValue: null } });
        await flushPromises();
        await wrapper.setProps({ modelValue: { latitude: 10, longitude: 20 } });

        expect(leaflet.L.marker).toHaveBeenCalledWith([10, 20], expect.objectContaining({ draggable: true }));
    });

    it('ignores clicks while disabled', async () => {
        const wrapper = mount(GuessMap, { props: { modelValue: null, disabled: true } });
        await flushPromises();

        leaflet.handlers.click!({ latlng: { lat: 1, lng: 1 } });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('removes the map on unmount', async () => {
        const wrapper = mount(GuessMap);
        await flushPromises();
        wrapper.unmount();
        expect(leaflet.map.remove).toHaveBeenCalled();
    });
});

describe('AnswerMap', () => {
    beforeEach(() => vi.clearAllMocks());

    it('shows both points, the line between them and fits the view', async () => {
        mount(AnswerMap, {
            props: { guess: { latitude: 48.85, longitude: 2.35 }, correct: { latitude: 41.9, longitude: 12.5 } },
        });
        await flushPromises();

        expect(leaflet.L.polyline).toHaveBeenCalledWith([[48.85, 2.35], [41.9, 12.5]], expect.any(Object));
        expect(leaflet.L.marker).toHaveBeenCalledTimes(2);
        expect(leaflet.L.latLngBounds).toHaveBeenCalledWith([[48.85, 2.35], [41.9, 12.5]]);
        expect(leaflet.map.fitBounds).toHaveBeenCalledTimes(1);
    });
});
