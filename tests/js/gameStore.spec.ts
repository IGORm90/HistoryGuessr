import { beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { ApiError } from '@/api/client';
import { gamesApi } from '@/api/games';
import { useGameStore } from '@/stores/game';
import { answerReveal, gameState } from './fixtures';

vi.mock('@/api/games', () => ({
    gamesApi: { start: vi.fn(), get: vi.fn(), answer: vi.fn(), advance: vi.fn(), result: vi.fn() },
}));

const api = vi.mocked(gamesApi);

function readyStore() {
    const store = useGameStore();
    store.applyState(gameState());
    store.guess = { latitude: 48.85, longitude: 2.35 };
    store.year = 1900;
    return store;
}

describe('game store', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.clearAllMocks();
    });

    it('derives the phase from server state', () => {
        const store = useGameStore();
        expect(store.phase).toBe('loading');
        store.applyState(gameState());
        expect(store.phase).toBe('guessing');
        store.applyState(gameState({ answer: answerReveal() }));
        expect(store.phase).toBe('revealed');
        store.applyState(gameState({ status: 'completed', question: null }));
        expect(store.phase).toBe('completed');
    });

    it('can submit only with both a point and a year', () => {
        const store = useGameStore();
        store.applyState(gameState());
        expect(store.canSubmit).toBe(false);
        store.guess = { latitude: 1, longitude: 2 };
        expect(store.canSubmit).toBe(false);
        store.year = 1900;
        expect(store.canSubmit).toBe(true);
    });

    it('resets the guess when the question changes', () => {
        const store = readyStore();
        store.applyState(gameState({ position: 2, question: { ...gameState().question!, id: 'q-2' } }));
        expect(store.guess).toBeNull();
        expect(store.year).toBeNull();
    });

    it('sends one request on a double click and applies the reveal', async () => {
        const store = readyStore();
        let resolve!: (value: ReturnType<typeof answerReveal>) => void;
        api.answer.mockReturnValue(new Promise((r) => (resolve = r)));

        const first = store.submit();
        const second = store.submit();
        resolve(answerReveal());
        await Promise.all([first, second]);

        expect(api.answer).toHaveBeenCalledTimes(1);
        expect(store.phase).toBe('revealed');
        expect(store.game?.total_score).toBe(2876);
    });

    it('reuses the idempotency key when retrying after a network error', async () => {
        const store = readyStore();
        api.answer.mockRejectedValueOnce(new ApiError(0, 'network_error', 'offline')).mockResolvedValueOnce(answerReveal());

        await store.submit();
        expect(store.error?.isRetryable).toBe(true);
        await store.submit();

        const [firstCall, secondCall] = api.answer.mock.calls;
        expect(firstCall![1].idempotency_key).toBe(secondCall![1].idempotency_key);
        expect(firstCall![1]).toMatchObject({ question_id: 'q-1', latitude: 48.85, longitude: 2.35, year: 1900 });
        expect(store.error).toBeNull();
    });

    it('resyncs from the server when another tab already answered', async () => {
        const store = readyStore();
        api.answer.mockRejectedValueOnce(new ApiError(409, 'already_answered', 'answered'));
        api.get.mockResolvedValueOnce(gameState({ answer: answerReveal() }));

        await store.submit();

        expect(api.get).toHaveBeenCalledWith('game-1');
        expect(store.phase).toBe('revealed');
    });

    it('advances from the current position', async () => {
        const store = useGameStore();
        store.applyState(gameState({ answer: answerReveal() }));
        api.advance.mockResolvedValueOnce(gameState({ position: 2, question: { ...gameState().question!, id: 'q-2' } }));

        await store.advance();

        expect(api.advance).toHaveBeenCalledWith('game-1', 1);
        expect(store.game?.position).toBe(2);
        expect(store.phase).toBe('guessing');
    });
});
