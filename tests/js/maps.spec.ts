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
            getSource: vi.fn((id: string) => sources[id]),
            addSource: vi.fn((id: string, source: { data: unknown }) => {
                const created: FakeSource = { data: source.data, setData: vi.fn((data) => (created.data = data)) };
                sources[id] = created;
            }),
            addLayer: vi.fn(),
        };
        return map;
    };

    const fakeMarker = (options: { draggable?: boolean }) => {
        const handlers: Record<string, () => void> = {};
        let lngLat: [number, number] = [0, 0];
        const marker = {
            options,
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
        createMarker: vi.fn((options: { draggable?: boolean } = {}) => {
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

import GuessMap from '@/components/maps/GuessMap.vue';
import AnswerMap from '@/components/maps/AnswerMap.vue';

const lastMap = () => maplibre.state.maps.at(-1)!;
const lastMarker = () => maplibre.state.markers.at(-1)!;

beforeEach(() => {
    vi.clearAllMocks();
    maplibre.state.maps.length = 0;
    maplibre.state.markers.length = 0;
    maplibre.state.failCreate = false;
    resizeObservers.length = 0;
    vi.spyOn(console, 'error').mockImplementation(() => {});
});

describe('GuessMap', () => {
    it('turns a click on a repeated world copy into named coordinates', async () => {
        const wrapper = mount(GuessMap, { props: { modelValue: null } });
        await flushPromises();

        lastMap().emit('click', { lngLat: { lat: 41.8954661, lng: 372.4767689 } });

        expect(wrapper.emitted('update:modelValue')![0]).toEqual([{ latitude: 41.895466, longitude: 12.476769 }]);
    });

    it('places, moves and removes the marker for the selected point', async () => {
        const wrapper = mount(GuessMap, { props: { modelValue: null } });
        await flushPromises();

        await wrapper.setProps({ modelValue: { latitude: 10, longitude: 20 } });
        expect(maplibre.createMarker).toHaveBeenCalledWith(expect.objectContaining({ draggable: true }));
        expect(lastMarker().setLngLat).toHaveBeenLastCalledWith([20, 10]);

        await wrapper.setProps({ modelValue: { latitude: -5, longitude: 30 } });
        expect(maplibre.createMarker).toHaveBeenCalledTimes(1);
        expect(lastMarker().setLngLat).toHaveBeenLastCalledWith([30, -5]);

        await wrapper.setProps({ modelValue: null });
        expect(lastMarker().remove).toHaveBeenCalled();
    });

    it('reports where the marker was dragged to', async () => {
        const wrapper = mount(GuessMap, { props: { modelValue: { latitude: 10, longitude: 20 } } });
        await flushPromises();

        lastMarker().setLngLat([-190, 45]);
        lastMarker().handlers.dragend!();

        expect(wrapper.emitted('update:modelValue')![0]).toEqual([{ latitude: 45, longitude: 170 }]);
    });

    it('ignores clicks and stops dragging while disabled', async () => {
        const wrapper = mount(GuessMap, { props: { modelValue: { latitude: 1, longitude: 1 } } });
        await flushPromises();

        await wrapper.setProps({ disabled: true });
        lastMap().emit('click', { lngLat: { lat: 2, lng: 2 } });

        expect(lastMarker().setDraggable).toHaveBeenCalledWith(false);
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('resizes the map with its container and removes it on unmount', async () => {
        const wrapper = mount(GuessMap);
        await flushPromises();

        resizeObservers[0]!.callback();
        expect(lastMap().resize).toHaveBeenCalled();

        wrapper.unmount();
        expect(resizeObservers[0]!.disconnect).toHaveBeenCalled();
        expect(lastMap().remove).toHaveBeenCalled();
    });

    it('shows an error with a retry when the map cannot be created', async () => {
        maplibre.state.failCreate = true;
        const wrapper = mount(GuessMap);
        await flushPromises();
        expect(wrapper.text()).toContain('Карта не загрузилась');

        maplibre.state.failCreate = false;
        await wrapper.get('button').trigger('click');

        expect(maplibre.state.maps).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Карта не загрузилась');
    });

    it('shows an error when the style fails before the first render', async () => {
        const wrapper = mount(GuessMap);
        await flushPromises();

        lastMap().emit('error', { error: new Error('style') });
        await flushPromises();

        expect(wrapper.text()).toContain('Карта не загрузилась');
    });
});

describe('AnswerMap', () => {
    const paris = { latitude: 48.85, longitude: 2.35 };
    const rome = { latitude: 41.9, longitude: 12.5 };

    it('draws both points and the line between them once the style has loaded, and fits the view', async () => {
        mount(AnswerMap, { props: { guess: paris, correct: rome } });
        await flushPromises();
        expect(lastMap().addSource).not.toHaveBeenCalled();

        lastMap().emit('load');
        await flushPromises();

        expect(lastMap().sources['answer-line']!.data).toMatchObject({
            geometry: { type: 'LineString', coordinates: [[2.35, 48.85], [12.5, 41.9]] },
        });
        expect(lastMap().addLayer).toHaveBeenCalledWith(expect.objectContaining({ type: 'line', source: 'answer-line' }));
        expect(maplibre.createMarker).toHaveBeenCalledTimes(2);
        expect(maplibre.LngLatBounds).toHaveBeenCalledWith([2.35, 48.85], [12.5, 41.9]);
        expect(lastMap().fitBounds).toHaveBeenCalledWith(expect.anything(), expect.objectContaining({ maxZoom: 7 }));
    });

    it('takes the short way across the antimeridian', async () => {
        mount(AnswerMap, {
            props: { guess: { latitude: 0, longitude: 179 }, correct: { latitude: 0, longitude: -179 } },
        });
        await flushPromises();
        lastMap().emit('load');
        await flushPromises();

        expect(lastMap().sources['answer-line']!.data).toMatchObject({
            geometry: { coordinates: [[179, 0], [181, 0]] },
        });
    });

    it('updates the line and markers in place when the answer changes', async () => {
        const wrapper = mount(AnswerMap, { props: { guess: paris, correct: rome } });
        await flushPromises();
        lastMap().emit('load');
        await flushPromises();

        await wrapper.setProps({ guess: { latitude: 52.52, longitude: 13.4 } });

        expect(maplibre.createMap).toHaveBeenCalledTimes(1);
        expect(maplibre.createMarker).toHaveBeenCalledTimes(2);
        expect(lastMap().addSource).toHaveBeenCalledTimes(1);
        expect(lastMap().sources['answer-line']!.setData).toHaveBeenCalledWith(
            expect.objectContaining({ geometry: { type: 'LineString', coordinates: [[13.4, 52.52], [12.5, 41.9]] } }),
        );
        expect(lastMap().fitBounds).toHaveBeenCalledTimes(2);
    });
});
