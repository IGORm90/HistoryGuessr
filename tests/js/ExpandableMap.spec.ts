import { afterEach, describe, expect, it } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import ExpandableMap from '@/components/maps/ExpandableMap.vue';

function setup() {
    const wrapper = mount(ExpandableMap, {
        attachTo: document.body,
        props: { expanded: false, 'onUpdate:expanded': (value: boolean) => wrapper.setProps({ expanded: value }) },
        slots: { default: '<div class="map-stub" />' },
    });
    return wrapper;
}

async function settle(): Promise<void> {
    await flushPromises();
    await new Promise((resolve) => setTimeout(resolve, 50));
}

describe('ExpandableMap', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('toggles from the button inside the map and keeps the same map element', async () => {
        const wrapper = setup();
        const map = wrapper.get('.map-stub').element;

        await wrapper.get('[aria-label="Увеличить карту"]').trigger('click');
        expect(wrapper.props('expanded')).toBe(true);
        expect(wrapper.get('button').attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('.map-stub').element).toBe(map);

        await wrapper.get('[aria-label="Уменьшить карту"]').trigger('click');
        expect(wrapper.props('expanded')).toBe(false);
    });

    it('collapses on Escape and on a click outside the map', async () => {
        const wrapper = setup();
        await wrapper.get('[aria-label="Увеличить карту"]').trigger('click');
        await settle();
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await flushPromises();
        expect(wrapper.props('expanded')).toBe(false);

        await wrapper.get('[aria-label="Увеличить карту"]').trigger('click');
        await settle();
        document.body.dispatchEvent(new Event('pointerdown', { bubbles: true }));
        await flushPromises();
        expect(wrapper.props('expanded')).toBe(false);
    });
});
