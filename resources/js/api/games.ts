import { api } from './client';
import type { AnswerReveal, GameResult, GameState, SubmitAnswerPayload } from '@/types/game';

export const gamesApi = {
    /** Without a quiz the server picks random published questions. */
    start: (quizId?: string) => api.post<GameState>('/game-sessions', quizId ? { quiz_id: quizId } : {}),
    get: (gameId: string) => api.get<GameState>(`/game-sessions/${gameId}`),
    answer: (gameId: string, payload: SubmitAnswerPayload) =>
        api.post<AnswerReveal>(`/game-sessions/${gameId}/answers`, payload),
    advance: (gameId: string, position: number) =>
        api.post<GameState>(`/game-sessions/${gameId}/advance`, { position }),
    result: (gameId: string) => api.get<GameResult>(`/game-sessions/${gameId}/result`),
};
