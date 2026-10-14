export type Coordinates = {
    latitude: number;
    longitude: number;
};

export type GameStatus = 'in_progress' | 'completed' | 'abandoned';

export type PublicQuestion = {
    id: string;
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
    /** The round ran out of time: no guess, zero points. */
    timed_out: boolean;
    guess: (Coordinates & { year: number }) | null;
    correct: Coordinates & { year: number; answer: string; location_name: string };
    distance_meters: number | null;
    year_difference: number | null;
    /** Measured on the server from the moment the question was shown. */
    duration_ms: number | null;
    score: Score;
    reveal_text: string;
    attribution: {
        author: string | null;
        license: string | null;
        source_url: string | null;
        text: string | null;
    };
};

/** Server-side round timer. Count down from `remaining_ms`: it does not depend on the client clock. */
export type RoundInfo = {
    time_limit_ms: number;
    /** Server tolerance after the deadline; never shown to the player. */
    grace_ms: number;
    started_at: string | null;
    deadline_at: string | null;
    /** Null once the question is answered (or for a round without a deadline). */
    remaining_ms: number | null;
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
    round: RoundInfo | null;
    /** One-off: set only in the response where the server skipped a round the player was away for. */
    notice: GameNotice | null;
};

export type GameNotice = 'previous_round_expired';

export type GameRound = {
    position: number;
    question: {
        id: string;
        answer: string;
        image_url: string | null;
        location_name: string | null;
        latitude: number | null;
        longitude: number | null;
        year: number | null;
    };
    timed_out: boolean;
    guess: (Coordinates & { year: number }) | null;
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

/** A regular answer has a point and a year; a timeout (`timed_out`) sends whatever was picked, possibly nothing. */
export type SubmitAnswerPayload = {
    question_id: string;
    latitude?: number;
    longitude?: number;
    year?: number;
    idempotency_key: string;
    timed_out?: boolean;
};
