export type Coordinates = {
    latitude: number;
    longitude: number;
};

export type GameStatus = 'in_progress' | 'completed' | 'abandoned';

export type PublicQuestion = {
    id: string;
    prompt: string | null;
    image: {
        medium_url: string | null;
        large_url: string | null;
        width: number | null;
        height: number | null;
    };
};

export type Score = {
    geo: number;
    year: number;
    total: number;
};

export type AnswerReveal = {
    question_id: string;
    guess: Coordinates & { year: number };
    correct: Coordinates & { year: number; location_name: string };
    distance_meters: number;
    year_difference: number;
    score: Score;
    reveal_text: string;
    attribution: {
        author: string | null;
        license: string | null;
        source_url: string | null;
        text: string | null;
    };
};

export type GameState = {
    id: string;
    status: GameStatus;
    quiz: { id: string; title: string };
    position: number;
    question_count: number;
    total_score: number;
    max_score: number;
    question: PublicQuestion | null;
    answer: AnswerReveal | null;
};

export type GameRound = {
    position: number;
    question: {
        id: string;
        prompt: string | null;
        image_url: string | null;
        location_name: string | null;
        year: number | null;
    };
    distance_meters: number | null;
    year_difference: number | null;
    score: { geo: number | null; year: number | null; total: number | null };
};

export type GameResult = {
    id: string;
    status: GameStatus;
    quiz: { id: string; title: string };
    total_score: number;
    max_score: number;
    started_at: string;
    completed_at: string | null;
    rounds: GameRound[];
};

export type SubmitAnswerPayload = {
    question_id: string;
    latitude: number;
    longitude: number;
    year: number;
    idempotency_key: string;
    duration_ms: number | null;
};
