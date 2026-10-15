import { acceptHMRUpdate, defineStore } from 'pinia';
import { computed, ref, shallowRef, watch } from 'vue';
import { ApiError } from '@/api/client';
import { gamesApi } from '@/api/games';
import { DEFAULT_YEAR } from '@/config/game';
import type { Coordinates, GameNotice, GameState, SubmitAnswerPayload } from '@/types/game';
import { uuid } from '@/utils/uuid';
import { forgetGame, rememberGame } from '@/utils/lastGame';

export type GamePhase = 'loading' | 'guessing' | 'revealed' | 'completed';

const TICK_MS = 250;
// Errors meaning the server state moved on without us: reload it instead of showing an error.
const RESYNC_CODES = ['already_answered', 'question_mismatch', 'game_not_in_progress', 'round_expired'];

/**
 * Client copy of the game. The server stays the source of truth: every mutation applies the
 * state it returns, and a reload restores everything via GET.
 */
export const useGameStore = defineStore('game', () => {
    const game = shallowRef<GameState | null>(null);
    const guess = ref<Coordinates | null>(null);
    const year = ref<number | null>(null);
    const submitting = ref(false);
    const advancing = ref(false);
    const error = shallowRef<ApiError | null>(null);

    // One idempotency key per question, kept until the answer is confirmed, so retries are safe.
    const pendingKeys = new Map<string, string>();
    // Round timer. `deadline` is local epoch ms derived from the server's `remaining_ms`, so a skewed
    // client clock doesn't matter; it is recomputed on every server state (reload, resync).
    const deadline = ref<number | null>(null);
    const now = ref(Date.now());
    let ticker: ReturnType<typeof setInterval> | undefined;
    // A one-off server message (e.g. a round skipped while the player was away), kept until dismissed.
    const notice = ref<GameNotice | null>(null);

    const phase = computed<GamePhase>(() => {
        if (!game.value) return 'loading';
        if (game.value.status !== 'in_progress') return 'completed';
        return game.value.answer ? 'revealed' : 'guessing';
    });

    const remainingMs = computed(() => (deadline.value === null ? null : Math.max(0, deadline.value - now.value)));
    // The player's time is over: whatever was picked is sent right away (`submitOnTimeout`).
    const timeUp = computed(() => phase.value === 'guessing' && remainingMs.value === 0);

    const canSubmit = computed(
        () => phase.value === 'guessing' && !timeUp.value && guess.value !== null && year.value !== null && !submitting.value,
    );

    function applyState(state: GameState): void {
        const questionChanged = state.question?.id !== game.value?.question?.id;
        // Set the new deadline before the new state: `timeUp` watchers run synchronously, and a new question
        // seen with the previous round's (already passed) deadline would be sent as timed out right away.
        now.value = Date.now();
        const remaining = state.answer ? null : (state.round?.remaining_ms ?? null);
        deadline.value = remaining === null ? null : now.value + remaining;
        game.value = state;
        if (questionChanged) {
            guess.value = null;
            year.value = null;
        }
        if (state.notice) {
            notice.value = state.notice;
        }
        if (state.status === 'in_progress') {
            rememberGame(state.id);
        } else {
            forgetGame(state.id);
        }
    }

    async function load(gameId: string): Promise<void> {
        error.value = null;
        if (game.value?.id !== gameId) {
            game.value = null;
        }
        try {
            applyState(await gamesApi.get(gameId));
        } catch (e) {
            if (e instanceof ApiError && e.status === 404) {
                forgetGame(gameId);
            }
            error.value = toApiError(e);
        }
    }

    async function start(quizId?: string): Promise<GameState> {
        const state = await gamesApi.start(quizId);
        applyState(state);
        return state;
    }

    async function submit(): Promise<void> {
        if (!canSubmit.value || !guess.value || year.value === null) {
            return;
        }
        await sendAnswer({ latitude: guess.value.latitude, longitude: guess.value.longitude, year: year.value });
    }

    /**
     * Sends what the player picked when the countdown hit zero: a point is scored as usual (an untouched
     * year ruler counts as DEFAULT_YEAR), no point scores zero. An answer already in flight decides instead.
     */
    async function submitOnTimeout(): Promise<void> {
        if (!timeUp.value || submitting.value) {
            return;
        }
        const point = guess.value;
        await sendAnswer(
            point ? { latitude: point.latitude, longitude: point.longitude, year: year.value ?? DEFAULT_YEAR, timed_out: true } : { timed_out: true },
        );
    }

    async function sendAnswer(guessPayload: Omit<SubmitAnswerPayload, 'question_id' | 'idempotency_key'>): Promise<void> {
        const state = game.value;
        const question = state?.question;
        if (!state || !question || phase.value !== 'guessing' || submitting.value) {
            return;
        }

        submitting.value = true;
        error.value = null;
        notice.value = null;
        // One key per question, shared by a manual answer and a timeout, so any retry is safe.
        const key = pendingKeys.get(question.id) ?? uuid();
        pendingKeys.set(question.id, key);

        try {
            const answer = await gamesApi.answer(state.id, { question_id: question.id, idempotency_key: key, ...guessPayload });
            pendingKeys.delete(question.id);
            // The round is over: its deadline must not outlive it into the next question.
            deadline.value = null;
            // We only submit while unanswered, so adding the score once is correct even for a replayed request.
            game.value = { ...state, answer, total_score: state.total_score + answer.score.total };
        } catch (e) {
            const apiError = toApiError(e);
            if (RESYNC_CODES.includes(apiError.code)) {
                // Another tab moved the game on, or the round expired; resync with the server.
                await load(state.id);
                return;
            }
            error.value = apiError;
        } finally {
            submitting.value = false;
        }
    }

    async function advance(): Promise<void> {
        const state = game.value;
        if (!state || phase.value !== 'revealed' || advancing.value) {
            return;
        }
        advancing.value = true;
        error.value = null;
        notice.value = null;
        try {
            applyState(await gamesApi.advance(state.id, state.position));
        } catch (e) {
            error.value = toApiError(e);
        } finally {
            advancing.value = false;
        }
    }

    /**
     * Background tabs throttle timers: refresh the clock when the tab becomes visible again.
     */
    function syncClock(): void {
        now.value = Date.now();
        void submitOnTimeout();
    }

    function dismissNotice(): void {
        notice.value = null;
    }

    function stopTimers(): void {
        clearInterval(ticker);
        ticker = undefined;
    }

    // Tick only while a deadline is running.
    watch([phase, deadline], () => {
        clearInterval(ticker);
        ticker = undefined;
        if (phase.value === 'guessing' && deadline.value !== null) {
            ticker = setInterval(() => {
                now.value = Date.now();
            }, TICK_MS);
        }
    }, { flush: 'sync' });

    watch(timeUp, (up) => {
        if (up) void submitOnTimeout();
    }, { flush: 'sync' });

    function reset(): void {
        stopTimers();
        game.value = null;
        guess.value = null;
        year.value = null;
        error.value = null;
        deadline.value = null;
        notice.value = null;
        pendingKeys.clear();
    }

    return {
        game,
        guess,
        year,
        submitting,
        advancing,
        error,
        phase,
        canSubmit,
        remainingMs,
        timeUp,
        notice,
        applyState,
        load,
        start,
        submit,
        submitOnTimeout,
        advance,
        syncClock,
        dismissNotice,
        reset,
    };
});

function toApiError(e: unknown): ApiError {
    return e instanceof ApiError ? e : new ApiError(0, 'unknown', 'Что-то пошло не так');
}

// In dev, swap the store's code on edit; without this Pinia keeps the old store until a full page reload.
if (import.meta.hot) {
    import.meta.hot.accept(acceptHMRUpdate(useGameStore, import.meta.hot));
}
