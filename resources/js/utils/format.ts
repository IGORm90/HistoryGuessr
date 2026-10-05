const numberFormat = new Intl.NumberFormat('ru-RU');

export function formatNumber(value: number): string {
    return numberFormat.format(value);
}

export function formatDistance(meters: number): string {
    if (meters < 1000) {
        return `${formatNumber(Math.round(meters))} м`;
    }
    const km = meters / 1000;
    return `${formatNumber(km < 10 ? Math.round(km * 10) / 10 : Math.round(km))} км`;
}

/** Russian plural: forms for 1, 2–4 and 5+ (e.g. ['год', 'года', 'лет']). */
export function plural(count: number, forms: [string, string, string]): string {
    const mod10 = count % 10;
    const mod100 = count % 100;
    const form = mod10 === 1 && mod100 !== 11 ? forms[0] : mod10 >= 2 && mod10 <= 4 && (mod100 < 12 || mod100 > 14) ? forms[1] : forms[2];
    return `${formatNumber(count)} ${form}`;
}

export function pluralYears(count: number): string {
    return plural(count, ['год', 'года', 'лет']);
}
