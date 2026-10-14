import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import RoundTimer from '@/components/game/RoundTimer.vue';

function timer(props: { remainingMs?: number | null; spentMs?: number | null; timedOut?: boolean }) {
    return mount(RoundTimer, { props: { remainingMs: null, spentMs: null, timedOut: false, ...props } });
}

describe('RoundTimer', () => {
    it('counts down from the full limit', () => {
        expect(timer({ remainingMs: 90_000 }).text()).toBe('1:30');
        expect(timer({ remainingMs: 89_001 }).text()).toBe('1:30');
        expect(timer({ remainingMs: 61_000 }).text()).toBe('1:01');
        expect(timer({ remainingMs: 0 }).text()).toBe('0:00');
    });

    it('pulses in the last 15 seconds', () => {
        expect(timer({ remainingMs: 15_001 }).attributes('data-urgent')).toBeUndefined();

        const urgent = timer({ remainingMs: 15_000 });
        expect(urgent.attributes('data-urgent')).toBe('true');
        expect(urgent.classes()).toContain('animate-pulse');
        expect(timer({ remainingMs: 15_001 }).classes()).not.toContain('animate-pulse');
    });

    it('shows the time the answer took on the reveal', () => {
        const wrapper = timer({ spentMs: 42_500 });
        expect(wrapper.text()).toBe('0:42');
        expect(wrapper.classes()).toContain('text-ink');
    });

    it('says when the time ran out', () => {
        const wrapper = timer({ remainingMs: 0, spentMs: 90_000, timedOut: true });
        expect(wrapper.text()).toBe('Время вышло');
        expect(wrapper.classes()).toContain('text-stamp');
        expect(wrapper.classes()).not.toContain('animate-pulse');
    });

    it('shows a dash without timing data', () => {
        expect(timer({}).text()).toBe('—');
    });
});
