import type { AnswerReveal, GameState } from '@/types/game';

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
            prompt: 'Где и когда?',
            image: { medium_url: '/m.webp', large_url: '/l.webp', width: 1600, height: 1067 },
        },
        answer: null,
        ...overrides,
    };
}

export function answerReveal(overrides: Partial<AnswerReveal> = {}): AnswerReveal {
    return {
        question_id: 'q-1',
        guess: { latitude: 48.85, longitude: 2.35, year: 1900 },
        correct: { latitude: 41.9, longitude: 12.5, year: -44, location_name: 'Рим' },
        distance_meters: 1_106_000,
        year_difference: 1943,
        score: { geo: 2876, year: 0, total: 2876 },
        reveal_text: 'Мартовские иды.',
        attribution: { author: null, license: 'Public domain', source_url: 'https://example.com', text: null },
        ...overrides,
    };
}
