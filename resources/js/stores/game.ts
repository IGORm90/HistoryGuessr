import { defineStore } from 'pinia';
import { computed, ref, shallowRef } from 'vue';
import { ApiError } from '@/api/client';
import { gamesApi } from '@/api/games';
import type { Coordinates, GameState } from '@/types/game';
import { uuid } from '@/utils/uuid';
import { forgetGame, rememberGame } from '@/utils/lastGame';

export type GamePhase = 'loading' | 'guessing' | 'revealed' | 'completed';

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
    let questionShownAt = 0;

    const phase = computed<GamePhase>(() => {
        if (!game.value) return 'loading';
        if (game.value.status !== 'in_progress') return 'completed';
        return game.value.answer ? 'revealed' : 'guessing';
    });

    const canSubmit = computed(
        () => phase.value === 'guessing' && guess.value !== null && year.value !== null && !submitting.value,
    );

    function applyState(state: GameState): void {
        const questionChanged = state.question?.id !== game.value?.question?.id;
        game.value = state;
        if (questionChanged) {
            guess.value = null;
            year.value = null;
            questionShownAt = Date.now();
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

    async function start(quizId: string): Promise<GameState> {
        const state = await gamesApi.start(quizId);
        applyState(state);
        return state;
    }

    async function submit(): Promise<void> {
        const state = game.value;
        const question = state?.question;
        if (!canSubmit.value || !state || !question || !guess.value || year.value === null) {
            return;
        }

        submitting.value = true;
        error.value = null;
        const key = pendingKeys.get(question.id) ?? uuid();
        pendingKeys.set(question.id, key);

        try {
            const answer = await gamesApi.answer(state.id, {
                question_id: question.id,
                latitude: guess.value.latitude,
                longitude: guess.value.longitude,
                year: year.value,
                idempotency_key: key,
                duration_ms: questionShownAt ? Date.now() - questionShownAt : null,
            });
            pendingKeys.delete(question.id);
            // We only submit while unanswered, so adding the score once is correct even for a replayed request.
            game.value = { ...state, answer, total_score: state.total_score + answer.score.total };
        } catch (e) {
            const apiError = toApiError(e);
            if (apiError.code === 'already_answered' || apiError.code === 'question_mismatch' || apiError.code === 'game_not_in_progress') {
                // Another tab moved the game on; resync with the server.
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
        try {
            applyState(await gamesApi.advance(state.id, state.position));
        } catch (e) {
            error.value = toApiError(e);
        } finally {
            advancing.value = false;
        }
    }

    function reset(): void {
        game.value = null;
        guess.value = null;
        year.value = null;
        error.value = null;
        pendingKeys.clear();
    }

    return { game, guess, year, submitting, advancing, error, phase, canSubmit, applyState, load, start, submit, advance, reset };
});

function toApiError(e: unknown): ApiError {
    return e instanceof ApiError ? e : new ApiError(0, 'unknown', 'Что-то пошло не так');
}
