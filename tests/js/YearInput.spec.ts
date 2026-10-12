import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import YearInput from '@/components/game/YearInput.vue';

function setup(modelValue: number | null = null, props: Record<string, unknown> = {}) {
    const wrapper = mount(YearInput, {
        props: {
            modelValue,
            'onUpdate:modelValue': (value: number | null) => wrapper.setProps({ modelValue: value }),
            ...props,
        },
    });
    return wrapper;
}

const slider = (wrapper: ReturnType<typeof setup>) => wrapper.get('[role="slider"]');

describe('YearInput', () => {
    beforeEach(() => {
        // jsdom has no canvas; reduced motion makes every move instant.
        vi.spyOn(HTMLCanvasElement.prototype, 'getContext').mockReturnValue(null);
        vi.stubGlobal('matchMedia', (query: string) => ({ matches: query.includes('reduce'), media: query }));
    });

    afterEach(() => {
        vi.restoreAllMocks();
        vi.unstubAllGlobals();
    });

    it('stays empty until the ruler is touched', () => {
        const wrapper = setup();
        expect(wrapper.props('modelValue')).toBeNull();
        expect(wrapper.get('[data-test="century"]').text()).toBe('не выбран');
        expect(slider(wrapper).attributes('aria-valuenow')).toBeUndefined();
    });

    it('steps from 1900 with the arrow keys', async () => {
        const wrapper = setup();
        await slider(wrapper).trigger('keydown', { key: 'ArrowRight' });
        expect(wrapper.props('modelValue')).toBe(1901);
        await slider(wrapper).trigger('keydown', { key: 'ArrowLeft', shiftKey: true });
        expect(wrapper.props('modelValue')).toBe(1891);
    });

    it('moves by a century with the ±100 buttons', async () => {
        const wrapper = setup(1500);
        await wrapper.get('[aria-label="На 100 лет позже"]').trigger('click');
        expect(wrapper.props('modelValue')).toBe(1600);
        await wrapper.get('[aria-label="На 100 лет раньше"]').trigger('click');
        await wrapper.get('[aria-label="На 100 лет раньше"]').trigger('click');
        expect(wrapper.props('modelValue')).toBe(1400);
    });

    it('skips year 0 between AD 1 and 1 BC', async () => {
        const wrapper = setup(1);
        await slider(wrapper).trigger('keydown', { key: 'ArrowLeft' });
        expect(wrapper.props('modelValue')).toBe(-1);
        await slider(wrapper).trigger('keydown', { key: 'ArrowRight' });
        expect(wrapper.props('modelValue')).toBe(1);
    });

    it('keeps the year between 1000 BC and AD 2020', async () => {
        const wrapper = setup(1980);
        await wrapper.get('[aria-label="На 100 лет позже"]').trigger('click');
        expect(wrapper.props('modelValue')).toBe(2020);
        await slider(wrapper).trigger('keydown', { key: 'Home' });
        expect(wrapper.props('modelValue')).toBe(-1000);
        await wrapper.get('[aria-label="На 100 лет раньше"]').trigger('click');
        expect(wrapper.props('modelValue')).toBe(-1000);
    });

    it('shows BC years with their era and century', () => {
        const wrapper = setup(-490);
        expect(wrapper.get('[data-test="year"]').text()).toBe('490до н. э.');
        expect(wrapper.get('[data-test="century"]').text()).toBe('V век до н. э.');
        expect(slider(wrapper).attributes('aria-valuetext')).toBe('490 г. до н. э.');
    });

    it('shows the century of the chosen year', () => {
        const wrapper = setup(1908);
        expect(wrapper.text()).toContain('XX век');
    });

    it('returns to the empty state when the model is reset', async () => {
        const wrapper = setup(1500);
        await wrapper.setProps({ modelValue: null });
        expect(wrapper.get('[data-test="century"]').text()).toBe('не выбран');
        expect(wrapper.get('[data-test="year"]').text()).toContain('1900');
    });

    it('ignores input while disabled', async () => {
        const wrapper = setup(null, { disabled: true });
        await slider(wrapper).trigger('keydown', { key: 'ArrowRight' });
        await wrapper.get('[aria-label="На 100 лет позже"]').trigger('click');
        expect(wrapper.props('modelValue')).toBeNull();
    });
});
