import { describe, expect, it } from 'vitest';
import { formatYear, fromSignedYear, isValidYear, toSignedYear, yearDifference } from '@/utils/year';

describe('year utils', () => {
    it('formats AD and BC years', () => {
        expect(formatYear(1921)).toBe('1921 г.');
        expect(formatYear(-44)).toBe('44 г. до н. э.');
    });

    it('converts between number + era and signed years', () => {
        expect(toSignedYear(44, 'bce')).toBe(-44);
        expect(toSignedYear(1921, 'ce')).toBe(1921);
        expect(fromSignedYear(-490)).toEqual({ absolute: 490, era: 'bce' });
    });

    it('rejects year zero and out-of-range years', () => {
        expect(toSignedYear(0, 'ce')).toBeNull();
        expect(toSignedYear(3000, 'ce')).toBeNull();
        expect(isValidYear(0)).toBe(false);
        expect(isValidYear(-10000)).toBe(true);
    });

    it('skips the non-existent year zero in differences', () => {
        expect(yearDifference(-1, 1)).toBe(1);
        expect(yearDifference(-44, 14)).toBe(57);
        expect(yearDifference(1900, 1921)).toBe(21);
    });
});
