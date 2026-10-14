import type { AnswerReveal, GameState, RoundInfo } from '@/types/game';

export function gameState(overrides: Partial<GameState> = {}): GameState {
    return {
        id: 'game-1',
        status: 'in_progress',
        quiz: { id: 'quiz-1', title: 'Подборка' },
        position: 1,
        question_count: 3,
        total_score: 0,
        max_score: 30000,
        question: {
            id: 'q-1',
            image: { medium_url: '/m.webp', large_url: '/l.webp', width: 1600, height: 1067 },
        },
        answer: null,
        round: roundInfo(),
        notice: null,
        ...overrides,
    };
}

export function answerReveal(overrides: Partial<AnswerReveal> = {}): AnswerReveal {
    return {
        question_id: 'q-1',
        timed_out: false,
        guess: { latitude: 48.85, longitude: 2.35, year: 1900 },
        correct: { latitude: 41.9, longitude: 12.5, year: -44, answer: 'Убийство Юлия Цезаря', location_name: 'Рим' },
        distance_meters: 1_106_000,
        year_difference: 1943,
        duration_ms: 42_000,
        score: { geo: 2876, year: 0, total: 2876 },
        reveal_text: 'Мартовские иды.',
        attribution: { author: null, license: 'Public domain', source_url: 'https://example.com', text: null },
        ...overrides,
    };
}

export function roundInfo(overrides: Partial<RoundInfo> = {}): RoundInfo {
    return {
        time_limit_ms: 90_000,
        grace_ms: 3_000,
        started_at: '2026-10-14T10:00:00.000Z',
        deadline_at: '2026-10-14T10:01:30.000Z',
        remaining_ms: 90_000,
        ...overrides,
    };
}

export function timedOutReveal(overrides: Partial<AnswerReveal> = {}): AnswerReveal {
    return answerReveal({
        timed_out: true,
        guess: null,
        distance_meters: null,
        year_difference: null,
        duration_ms: 90_000,
        score: { geo: 0, year: 0, total: 0 },
        ...overrides,
    });
}
