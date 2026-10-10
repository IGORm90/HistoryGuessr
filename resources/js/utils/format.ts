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

/** Coordinates for a caption: «55.8° с. ш. · 37.6° в. д.». */
export function formatCoordinates(point: { latitude: number; longitude: number }): string {
    const lat = `${Math.abs(point.latitude).toFixed(1)}° ${point.latitude < 0 ? 'ю. ш.' : 'с. ш.'}`;
    const lng = `${Math.abs(point.longitude).toFixed(1)}° ${point.longitude < 0 ? 'з. д.' : 'в. д.'}`;
    return `${lat} · ${lng}`;
}

/** Elapsed time as «m:ss». */
export function formatClock(ms: number): string {
    const total = Math.max(0, Math.floor(ms / 1000));
    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}
