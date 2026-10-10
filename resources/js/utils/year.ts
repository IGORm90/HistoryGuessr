/**
 * Years are signed integers: -44 is 44 BC. Year 0 does not exist.
 * Mirrors App\Support\HistoricalYear on the backend.
 */
export const MIN_YEAR = -10000;
export const MAX_YEAR = 2100;

export type Era = 'ce' | 'bce';

export function isValidYear(year: number): boolean {
    return Number.isInteger(year) && year !== 0 && year >= MIN_YEAR && year <= MAX_YEAR;
}

export function toSignedYear(absolute: number, era: Era): number | null {
    if (!Number.isInteger(absolute) || absolute < 1) {
        return null;
    }
    const year = era === 'bce' ? -absolute : absolute;
    return isValidYear(year) ? year : null;
}

export function fromSignedYear(year: number): { absolute: number; era: Era } {
    return { absolute: Math.abs(year), era: year < 0 ? 'bce' : 'ce' };
}

export function formatYear(year: number): string {
    return year < 0 ? `${Math.abs(year)} г. до н. э.` : `${year} г.`;
}

export function yearDifference(a: number, b: number): number {
    const diff = Math.abs(a - b);
    return a < 0 !== b < 0 ? diff - 1 : diff;
}

function toRoman(value: number): string {
    const numerals: [number, string][] = [
        [100, 'C'], [90, 'XC'], [50, 'L'], [40, 'XL'], [10, 'X'], [9, 'IX'], [5, 'V'], [4, 'IV'], [1, 'I'],
    ];
    let rest = value;
    let result = '';
    for (const [amount, numeral] of numerals) {
        while (rest >= amount) {
            result += numeral;
            rest -= amount;
        }
    }
    return result;
}

/** Century in Roman numerals: 1908 → «XX век», -44 → «I век до н. э.». */
export function formatCentury(year: number): string {
    const century = toRoman(Math.ceil(Math.abs(year) / 100));
    return year < 0 ? `${century} век до н. э.` : `${century} век`;
}
