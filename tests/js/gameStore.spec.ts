import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import { ApiError } from '@/api/client';
import { gamesApi } from '@/api/games';
import { useGameStore } from '@/stores/game';
import { answerReveal, gameState, roundInfo, timedOutReveal } from './fixtures';

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
        vi.resetAllMocks();
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

    describe('round timer', () => {
        beforeEach(() => {
            vi.useFakeTimers();
        });

        afterEach(() => {
            vi.useRealTimers();
        });

        it('counts down from the server remaining time', () => {
            const store = useGameStore();
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 60_000 }) }));
            expect(store.remainingMs).toBe(60_000);

            vi.advanceTimersByTime(10_000);
            expect(store.remainingMs).toBe(50_000);
        });

        it('does not restart the clock when the same question is applied again', () => {
            const store = useGameStore();
            store.applyState(gameState());
            vi.advanceTimersByTime(30_000);

            store.applyState(gameState({ round: roundInfo({ remaining_ms: 60_000 }) }));

            expect(store.remainingMs).toBe(60_000);
        });

        it('has no countdown once the question is answered', () => {
            const store = useGameStore();
            store.applyState(gameState({ answer: answerReveal(), round: roundInfo({ remaining_ms: null }) }));
            expect(store.remainingMs).toBeNull();
            expect(store.timeUp).toBe(false);
        });

        it('sends the picked point with the default year right at zero', async () => {
            const store = useGameStore();
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 5_000 }) }));
            store.guess = { latitude: 48.85, longitude: 2.35 };
            api.answer.mockResolvedValueOnce(answerReveal({ timed_out: true }));

            await vi.advanceTimersByTimeAsync(5_000);

            expect(api.answer).toHaveBeenCalledTimes(1);
            expect(api.answer.mock.calls[0]![1]).toMatchObject({
                question_id: 'q-1',
                latitude: 48.85,
                longitude: 2.35,
                year: 1900,
                timed_out: true,
            });
            expect(api.get).not.toHaveBeenCalled();
            expect(store.phase).toBe('revealed');
            expect(store.game?.total_score).toBe(2876);
        });

        it('sends the year the player picked', async () => {
            const store = readyStore();
            store.year = -44;
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 1_000 }) }));
            api.answer.mockResolvedValueOnce(answerReveal({ timed_out: true }));

            await vi.advanceTimersByTimeAsync(1_000);

            expect(api.answer.mock.calls[0]![1]).toMatchObject({ year: -44, timed_out: true });
        });

        it('sends an empty timeout without a point', async () => {
            const store = useGameStore();
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 1_000 }) }));
            store.year = 1950;
            api.answer.mockResolvedValueOnce(timedOutReveal());

            await vi.advanceTimersByTimeAsync(1_000);

            const payload = api.answer.mock.calls[0]![1];
            expect(payload).toMatchObject({ question_id: 'q-1', timed_out: true });
            expect(payload).not.toHaveProperty('latitude');
            expect(payload).not.toHaveProperty('year');
            expect(store.phase).toBe('revealed');
            expect(store.game?.answer?.guess).toBeNull();
        });

        it('lets an answer in flight decide the round', async () => {
            const store = readyStore();
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 1_000 }) }));
            let resolve!: (value: ReturnType<typeof answerReveal>) => void;
            api.answer.mockReturnValue(new Promise((r) => (resolve = r)));

            const submitting = store.submit();
            await vi.advanceTimersByTimeAsync(5_000);
            expect(store.timeUp).toBe(true);
            expect(store.canSubmit).toBe(false);

            resolve(answerReveal());
            await submitting;

            expect(api.answer).toHaveBeenCalledTimes(1);
            expect(api.answer.mock.calls[0]![1]).not.toHaveProperty('timed_out');
            expect(store.game?.answer?.timed_out).toBe(false);
        });

        it('retries a failed timeout with the same key', async () => {
            const store = readyStore();
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 1_000 }) }));
            api.answer
                .mockRejectedValueOnce(new ApiError(0, 'network_error', 'offline'))
                .mockResolvedValueOnce(answerReveal({ timed_out: true }));

            await vi.advanceTimersByTimeAsync(1_000);
            expect(store.error?.code).toBe('network_error');
            expect(store.phase).toBe('guessing');

            await store.submitOnTimeout();

            const [first, second] = api.answer.mock.calls;
            expect(second![1].idempotency_key).toBe(first![1].idempotency_key);
            expect(second![1].timed_out).toBe(true);
            expect(store.phase).toBe('revealed');
        });

        it('does not time out the next question after a long look at the reveal', async () => {
            const store = useGameStore();
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 30_000 }) }));
            store.guess = { latitude: 48.85, longitude: 2.35 };
            store.year = 1900;
            api.answer.mockResolvedValueOnce(answerReveal());
            await store.submit();
            expect(store.remainingMs).toBeNull();

            // The previous round's deadline passes while the player reads the answer.
            await vi.advanceTimersByTimeAsync(60_000);
            api.advance.mockResolvedValueOnce(gameState({ position: 2, question: { ...gameState().question!, id: 'q-2' }, round: roundInfo({ remaining_ms: 90_000 }) }));
            await store.advance();
            await vi.advanceTimersByTimeAsync(1_000);

            expect(api.answer).toHaveBeenCalledTimes(1);
            expect(store.phase).toBe('guessing');
            expect(store.timeUp).toBe(false);
            expect(store.error).toBeNull();
            expect(store.remainingMs).toBe(89_000);
        });

        it('resyncs when the server reports the round expired', async () => {
            const store = readyStore();
            api.answer.mockRejectedValueOnce(new ApiError(409, 'round_expired', 'late'));
            const next = gameState({ position: 2, question: { ...gameState().question!, id: 'q-2' }, notice: 'previous_round_expired' });
            api.get.mockResolvedValueOnce(next);

            await store.submit();

            expect(api.get).toHaveBeenCalledWith('game-1');
            expect(store.error).toBeNull();
            expect(store.game?.position).toBe(2);
            expect(store.phase).toBe('guessing');
        });

        it('sends the timeout as soon as a background tab comes back', async () => {
            const store = readyStore();
            store.applyState(gameState({ round: roundInfo({ remaining_ms: 1_000 }) }));
            api.answer.mockResolvedValueOnce(answerReveal({ timed_out: true }));
            // Throttled background timers: the clock jumps without ticks.
            vi.setSystemTime(Date.now() + 2_000);

            store.syncClock();
            await vi.advanceTimersByTimeAsync(0);

            expect(api.answer).toHaveBeenCalledTimes(1);
            expect(api.answer.mock.calls[0]![1].timed_out).toBe(true);
        });
    });

    describe('notice', () => {
        it('keeps the skipped-round notice until dismissed', () => {
            const store = useGameStore();
            store.applyState(gameState({ notice: 'previous_round_expired' }));
            expect(store.notice).toBe('previous_round_expired');

            // A later read without the notice does not hide it by itself.
            store.applyState(gameState());
            expect(store.notice).toBe('previous_round_expired');

            store.dismissNotice();
            expect(store.notice).toBeNull();
        });

        it('hides the notice once the player answers', async () => {
            const store = readyStore();
            store.applyState(gameState({ notice: 'previous_round_expired' }));
            api.answer.mockResolvedValueOnce(answerReveal());

            await store.submit();

            expect(store.notice).toBeNull();
        });
    });
});
