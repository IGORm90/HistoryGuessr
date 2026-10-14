import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ScoreBreakdown from '@/components/game/ScoreBreakdown.vue';
import { answerReveal, timedOutReveal } from './fixtures';

describe('ScoreBreakdown', () => {
    it('shows the correct answer, errors and points', () => {
        const text = mount(ScoreBreakdown, { props: { answer: answerReveal() } }).text().replace(/\s+/g, ' ');

        expect(text).toContain('Убийство Юлия Цезаря');
        expect(text).toContain('Рим');
        expect(text).toContain('44 до н. э.');
        expect(text).toContain('1 106 км от цели');
        expect(text).toContain('1900 г., ошибка 1 943 года');
        expect(text).toContain('+2 876');
        expect(text).toContain('Мартовские иды.');
        expect(text).toContain('Public domain');
    });

    it('praises an exact year', () => {
        const wrapper = mount(ScoreBreakdown, {
            props: { answer: answerReveal({ year_difference: 0, guess: { latitude: 0, longitude: 0, year: -44 } }) },
        });
        expect(wrapper.text()).toContain('точно в год');
    });

    it('stamps a timed-out round without a point and shows no distance or year error', () => {
        const text = mount(ScoreBreakdown, { props: { answer: timedOutReveal() } }).text().replace(/\s+/g, ' ');

        expect(text).toContain('Время вышло — 0 очков');
        expect(text).not.toContain('засчитан выбранный ответ');
        expect(text).toContain('+0');
        expect(text).toContain('Убийство Юлия Цезаря');
        expect(text).not.toContain('от цели');
        expect(text).not.toContain('ошибка');
    });

    it('scores the point picked when time ran out and says so', () => {
        const text = mount(ScoreBreakdown, { props: { answer: answerReveal({ timed_out: true }) } }).text().replace(/\s+/g, ' ');

        expect(text).toContain('Время вышло — засчитан выбранный ответ');
        expect(text).not.toContain('0 очков');
        expect(text).toContain('1 106 км от цели');
        expect(text).toContain('+2 876');
    });
});
