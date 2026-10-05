/** Remembers the last unfinished game for the "continue" button. Storage may be unavailable. */
const KEY = 'historyguessr:last-game';

export function rememberGame(gameId: string): void {
    try {
        localStorage.setItem(KEY, gameId);
    } catch {
        // Private mode or blocked storage: the feature simply stays off.
    }
}

export function lastGame(): string | null {
    try {
        return localStorage.getItem(KEY);
    } catch {
        return null;
    }
}

export function forgetGame(gameId?: string): void {
    try {
        if (!gameId || localStorage.getItem(KEY) === gameId) {
            localStorage.removeItem(KEY);
        }
    } catch {
        // ignore
    }
}
