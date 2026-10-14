import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

type Handler = (event: { lngLat: { lng: number; lat: number } }) => void;

const maplibre = vi.hoisted(() => {
    type FakeSource = { data: unknown; setData: (data: unknown) => void };

    const fakeMap = () => {
        const handlers: Record<string, Handler[]> = {};
        const sources: Record<string, FakeSource> = {};
        const map = {
            handlers,
            sources,
            on: vi.fn((name: string, handler: Handler) => {
                (handlers[name] ??= []).push(handler);
                return map;
            }),
            emit: (name: string, event: unknown = {}) => handlers[name]?.forEach((handler) => handler(event as never)),
            remove: vi.fn(),
            resize: vi.fn(),
            fitBounds: vi.fn(),
            flyTo: vi.fn(),
            getSource: vi.fn((id: string) => sources[id]),
            addSource: vi.fn((id: string, source: { data: unknown }) => {
                const created: FakeSource = { data: source.data, setData: vi.fn((data) => (created.data = data)) };
                sources[id] = created;
            }),
            addLayer: vi.fn(),
        };
        return map;
    };

    type MarkerOptions = { draggable?: boolean; element?: HTMLElement };

    const fakeMarker = (options: MarkerOptions) => {
        const handlers: Record<string, () => void> = {};
        let lngLat: [number, number] = [0, 0];
        const element = options.element ?? document.createElement('div');
        const marker = {
            options,
            element,
            getElement: () => element,
            handlers,
            setLngLat: vi.fn((value: [number, number]) => {
                lngLat = value;
                return marker;
            }),
            getLngLat: () => ({ lng: lngLat[0], lat: lngLat[1] }),
            addTo: vi.fn(() => marker),
            on: vi.fn((name: string, handler: () => void) => {
                handlers[name] = handler;
                return marker;
            }),
            remove: vi.fn(),
            setDraggable: vi.fn(),
        };
        return marker;
    };

    const state = {
        maps: [] as ReturnType<typeof fakeMap>[],
        markers: [] as ReturnType<typeof fakeMarker>[],
        failCreate: false,
    };

    return {
        state,
        createMap: vi.fn(() => {
            if (state.failCreate) {
                throw new Error('WebGL is not supported');
            }
            const map = fakeMap();
            state.maps.push(map);
            return map;
        }),
        createMarker: vi.fn((options: MarkerOptions = {}) => {
            const marker = fakeMarker(options);
            state.markers.push(marker);
            return marker;
        }),
        LngLatBounds: vi.fn(function (this: { points: unknown[] }, ...points: unknown[]) {
            this.points = points;
        }),
    };
});

vi.mock('@/lib/maplibre', () => ({
    createMap: maplibre.createMap,
    createMarker: maplibre.createMarker,
    markerElement: (className: string) => Object.assign(document.createElement('div'), { className }),
    GRATICULE: 'graticule',
    LngLatBounds: maplibre.LngLatBounds,
}));

const resizeObservers: { callback: () => void; disconnect: ReturnType<typeof vi.fn> }[] = [];
vi.stubGlobal(
    'ResizeObserver',
    class {
        disconnect = vi.fn();
        constructor(public callback: () => void) {
            resizeObservers.push(this);
        }
        observe(): void {}
    },
);

import GameMap from '@/components/maps/GameMap.vue';
import type { MapReveal } from '@/components/maps/GameMap.vue';

const lastMap = () => maplibre.state.maps.at(-1)!;
const markers = (className: string) => maplibre.state.markers.filter((marker) => marker.element.className.includes(className));
const pin = () => markers('marker-guess').at(-1)!;
const visible = (className: string) => markers(className).filter((marker) => marker.remove.mock.calls.length === 0);

beforeEach(() => {
    vi.clearAllMocks();
    maplibre.state.maps.length = 0;
    maplibre.state.markers.length = 0;
    maplibre.state.failCreate = false;
    resizeObservers.length = 0;
    vi.spyOn(console, 'error').mockImplementation(() => {});
});

describe('GameMap while guessing', () => {
    it('turns a click on a repeated world copy into named coordinates', async () => {
        const wrapper = mount(GameMap, { props: { modelValue: null } });
        await flushPromises();

        lastMap().emit('click', { lngLat: { lat: 41.8954661, lng: 372.4767689 } });

        expect(wrapper.emitted('update:modelValue')![0]).toEqual([{ latitude: 41.895466, longitude: 12.476769 }]);
    });

    it('places, moves and removes the marker and its caption for the selected point', async () => {
        const wrapper = mount(GameMap, { props: { modelValue: null } });
        await flushPromises();

        await wrapper.setProps({ modelValue: { latitude: 10, longitude: 20 } });
        expect(maplibre.createMarker).toHaveBeenCalledWith(expect.objectContaining({ draggable: true }));
        expect(pin().setLngLat).toHaveBeenLastCalledWith([20, 10]);
        expect(markers('map-label')[0]!.element.textContent).toBe('Ваша отметка · 10.0° с. ш. · 20.0° в. д.');

        await wrapper.setProps({ modelValue: { latitude: -5, longitude: 30 } });
        expect(maplibre.createMarker).toHaveBeenCalledTimes(2);
        expect(pin().setLngLat).toHaveBeenLastCalledWith([30, -5]);
        expect(markers('map-label')[0]!.element.textContent).toBe('Ваша отметка · 5.0° ю. ш. · 30.0° в. д.');

        await wrapper.setProps({ modelValue: null });
        expect(pin().remove).toHaveBeenCalled();
        expect(markers('map-label')[0]!.remove).toHaveBeenCalled();
    });

    it('reports where the marker was dragged to and moves the caption along', async () => {
        const wrapper = mount(GameMap, { props: { modelValue: { latitude: 10, longitude: 20 } } });
        await flushPromises();

        pin().setLngLat([-190, 45]);
        pin().handlers.drag!();
        expect(markers('map-label')[0]!.element.textContent).toBe('Ваша отметка · 45.0° с. ш. · 170.0° в. д.');
        pin().handlers.dragend!();

        expect(wrapper.emitted('update:modelValue')![0]).toEqual([{ latitude: 45, longitude: 170 }]);
    });

    it('ignores clicks and stops dragging while disabled', async () => {
        const wrapper = mount(GameMap, { props: { modelValue: { latitude: 1, longitude: 1 } } });
        await flushPromises();

        await wrapper.setProps({ disabled: true });
        lastMap().emit('click', { lngLat: { lat: 2, lng: 2 } });

        expect(pin().setDraggable).toHaveBeenCalledWith(false);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('resizes the map with its container and removes it on unmount', async () => {
        const wrapper = mount(GameMap);
        await flushPromises();

        resizeObservers[0]!.callback();
        expect(lastMap().resize).toHaveBeenCalled();

        wrapper.unmount();
        expect(resizeObservers[0]!.disconnect).toHaveBeenCalled();
        expect(lastMap().remove).toHaveBeenCalled();
    });

    it('shows an error with a retry when the map cannot be created', async () => {
        maplibre.state.failCreate = true;
        const wrapper = mount(GameMap);
        await flushPromises();
        expect(wrapper.text()).toContain('Карта не загрузилась');

        maplibre.state.failCreate = false;
        await wrapper.get('button').trigger('click');

        expect(maplibre.state.maps).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Карта не загрузилась');
    });

    it('shows an error when the style fails before the first render', async () => {
        const wrapper = mount(GameMap);
        await flushPromises();

        lastMap().emit('error', { error: new Error('style') });
        await flushPromises();

        expect(wrapper.text()).toContain('Карта не загрузилась');
    });
});

describe('GameMap with the answer', () => {
    const paris = { latitude: 48.85, longitude: 2.35 };
    const rome = { latitude: 41.9, longitude: 12.5 };
    const reveal = (overrides: Partial<MapReveal> = {}): MapReveal => ({
        guess: paris,
        correct: rome,
        distanceMeters: 1_106_000,
        locationName: 'Рим',
        ...overrides,
    });
    const lineCoordinates = () => (lastMap().sources['answer-line']!.data as { geometry: { coordinates: number[][] } }).geometry.coordinates;

    it('draws both points, the geodesic and the captions once the style has loaded, and fits the view', async () => {
        mount(GameMap, { props: { modelValue: paris, reveal: reveal(), padding: { right: 540 } } });
        await flushPromises();
        expect(lastMap().addSource).not.toHaveBeenCalled();

        lastMap().emit('load');
        await flushPromises();

        expect(lineCoordinates()[0]).toEqual([2.35, 48.85]);
        expect(lineCoordinates().at(-1)).toEqual([12.5, 41.9]);
        expect(lastMap().addLayer).toHaveBeenCalledWith(expect.objectContaining({ type: 'line', source: 'answer-line' }));
        expect(visible('marker-guess')).toHaveLength(1);
        expect(visible('marker-guess')[0]!.element.className).toContain('marker-small');
        expect(visible('marker-correct')).toHaveLength(1);
        expect(markers('map-label--distance')[0]!.element.textContent).toMatch(/^1\s106 км$/);
        expect(markers('map-label--stamp')[0]!.element.textContent).toBe('Рим · 41.9° с. ш. · 12.5° в. д.');
        expect(lastMap().fitBounds).toHaveBeenCalledWith(
            expect.anything(),
            expect.objectContaining({ maxZoom: 7, padding: expect.objectContaining({ right: 540, left: 80 }) }),
        );
    });

    it('takes the short way across the antimeridian', async () => {
        mount(GameMap, {
            props: { reveal: reveal({ guess: { latitude: 0, longitude: 179 }, correct: { latitude: 0, longitude: -179 } }) },
        });
        await flushPromises();
        lastMap().emit('load');
        await flushPromises();

        expect(lineCoordinates()[0]).toEqual([179, 0]);
        expect(lineCoordinates().at(-1)).toEqual([181, 0]);
        expect(Math.max(...lineCoordinates().map(([longitude]) => longitude!))).toBe(181);
    });

    it('ignores clicks on the map once the answer is shown', async () => {
        const wrapper = mount(GameMap, { props: { modelValue: paris, reveal: reveal() } });
        await flushPromises();

        lastMap().emit('click', { lngLat: { lat: 2, lng: 2 } });

        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('redraws in place when the answer changes', async () => {
        const wrapper = mount(GameMap, { props: { reveal: reveal() } });
        await flushPromises();
        lastMap().emit('load');
        await flushPromises();

        await wrapper.setProps({ reveal: reveal() });
        expect(lastMap().fitBounds).toHaveBeenCalledTimes(1);

        await wrapper.setProps({ reveal: reveal({ guess: { latitude: 52.52, longitude: 13.4 } }) });

        expect(maplibre.createMap).toHaveBeenCalledTimes(1);
        expect(lastMap().addSource).toHaveBeenCalledTimes(1);
        expect(lineCoordinates()[0]).toEqual([13.4, 52.52]);
        expect(visible('marker-correct')).toHaveLength(1);
        expect(lastMap().fitBounds).toHaveBeenCalledTimes(2);
    });

    it('shows only the correct place after a timeout', async () => {
        const wrapper = mount(GameMap, { props: { reveal: reveal({ guess: null, distanceMeters: null }) } });
        await flushPromises();
        lastMap().emit('load');
        await flushPromises();

        expect(lastMap().sources['answer-line']!.data).toEqual({ type: 'FeatureCollection', features: [] });
        expect(visible('marker-guess')).toHaveLength(0);
        expect(visible('marker-correct')).toHaveLength(1);
        expect(lastMap().fitBounds).not.toHaveBeenCalled();
        expect(lastMap().flyTo).toHaveBeenCalledWith(expect.objectContaining({ center: [12.5, 41.9], zoom: 5 }));
        expect(wrapper.find('[role="img"]').attributes('aria-label')).toBe('Карта с правильным ответом');
    });

    it('clears the answer and returns to the world for the next question', async () => {
        const wrapper = mount(GameMap, { props: { modelValue: paris, reveal: reveal() } });
        await flushPromises();
        lastMap().emit('load');
        await flushPromises();

        await wrapper.setProps({ modelValue: null, reveal: null });

        expect(lastMap().sources['answer-line']!.data).toEqual({ type: 'FeatureCollection', features: [] });
        expect(visible('marker-correct')).toHaveLength(0);
        expect(visible('map-label')).toHaveLength(0);
        expect(lastMap().flyTo).toHaveBeenCalledWith(expect.objectContaining({ center: [10, 30], zoom: 1 }));
        expect(wrapper.find('[role="application"]').exists()).toBe(true);
    });
});
