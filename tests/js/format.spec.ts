import { describe, expect, it } from 'vitest';
import { formatClock, formatCoordinates, formatDistance, formatNumber, plural, pluralYears } from '@/utils/format';

describe('format utils', () => {
    it('formats scores with Russian grouping', () => {
        expect(formatNumber(12450).replace(/\s/g, ' ')).toBe('12 450');
    });

    it('formats distances in metres and kilometres', () => {
        expect(formatDistance(420)).toBe('420 м');
        expect(formatDistance(4321)).toBe('4,3 км');
        expect(formatDistance(1_383_400).replace(/\s/g, ' ')).toBe('1 383 км');
    });

    it('pluralises in Russian', () => {
        expect(pluralYears(1)).toBe('1 год');
        expect(pluralYears(3)).toBe('3 года');
        expect(pluralYears(11)).toBe('11 лет');
        expect(pluralYears(22)).toBe('22 года');
        expect(plural(5, ['вопрос', 'вопроса', 'вопросов'])).toBe('5 вопросов');
    });
});

describe('formatCoordinates', () => {
    it('names hemispheres in Russian', () => {
        expect(formatCoordinates({ latitude: 55.75, longitude: 37.62 })).toBe('55.8° с. ш. · 37.6° в. д.');
        expect(formatCoordinates({ latitude: -33.86, longitude: -70.65 })).toBe('33.9° ю. ш. · 70.7° з. д.');
    });
});

describe('formatClock', () => {
    it('formats elapsed milliseconds as m:ss', () => {
        expect(formatClock(0)).toBe('0:00');
        expect(formatClock(65_400)).toBe('1:05');
        expect(formatClock(-500)).toBe('0:00');
    });
});
