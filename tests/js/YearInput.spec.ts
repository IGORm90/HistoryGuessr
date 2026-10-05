import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import YearInput from '@/components/game/YearInput.vue';

function setup(modelValue: number | null = null) {
    const wrapper = mount(YearInput, {
        props: { modelValue, 'onUpdate:modelValue': (value: number | null) => wrapper.setProps({ modelValue: value }) },
    });
    return wrapper;
}

describe('YearInput', () => {
    it('emits a signed year for AD input', async () => {
        const wrapper = setup();
        await wrapper.get('input').setValue('1921');
        expect(wrapper.props('modelValue')).toBe(1921);
    });

    it('emits a negative year when "до н. э." is chosen', async () => {
        const wrapper = setup();
        await wrapper.get('input').setValue('44');
        await wrapper.findAll('[role="radio"]')[1]!.trigger('click');
        expect(wrapper.props('modelValue')).toBe(-44);
    });

    it('emits null and shows an error for invalid years', async () => {
        const wrapper = setup(1900);
        await wrapper.get('input').setValue('0');
        expect(wrapper.props('modelValue')).toBeNull();
        expect(wrapper.text()).toContain('Год — от 1 до 2100');
    });

    it('drops non-digit characters', async () => {
        const wrapper = setup();
        await wrapper.get('input').setValue('19a2-1');
        expect((wrapper.get('input').element as HTMLInputElement).value).toBe('1921');
        expect(wrapper.props('modelValue')).toBe(1921);
    });

    it('hydrates BC years', () => {
        const wrapper = setup(-490);
        expect((wrapper.get('input').element as HTMLInputElement).value).toBe('490');
        expect(wrapper.findAll('[role="radio"]')[1]!.attributes('aria-checked')).toBe('true');
    });
});
