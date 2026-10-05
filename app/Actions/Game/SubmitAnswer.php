<?php

namespace App\Actions\Game;

use App\DTO\Game\Coordinates;
use App\DTO\Game\SubmitAnswerData;
use App\Exceptions\GameException;
use App\Models\Answer;
use App\Models\GameSession;
use App\Services\QuestionCache;
use App\Services\ScoreCalculator;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

class SubmitAnswer
{
    public function __construct(
        private readonly ScoreCalculator $scores,
        private readonly QuestionCache $questions,
    ) {}

    /**
     * Scores and stores the guess for the current question. Idempotent per `idempotency_key`:
     * a retry returns the answer created by the first request.
     */
    public function handle(GameSession $session, SubmitAnswerData $data): Answer
    {
        try {
            return DB::transaction(fn (): Answer => $this->submit($session, $data));
        } catch (UniqueConstraintViolationException) {
            // A concurrent request with the same key won the race; return its result.
            return $this->findByKey($session, $data->idempotencyKey) ?? throw GameException::alreadyAnswered();
        }
    }

    private function submit(GameSession $session, SubmitAnswerData $data): Answer
    {
        // Serialise answers within one game session.
        $locked = GameSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();

        if ($existing = $this->findByKey($locked, $data->idempotencyKey)) {
            return $existing;
        }

        if (! $locked->isInProgress()) {
            throw GameException::notInProgress();
        }

        $current = DB::table('game_session_questions')
            ->join('questions', 'questions.id', '=', 'game_session_questions.question_id')
            ->where('game_session_questions.game_session_id', $locked->id)
            ->where('game_session_questions.position', $locked->current_position)
            ->first(['questions.id', 'questions.public_id']);

        if ($current === null || $current->public_id !== $data->questionPublicId) {
            throw GameException::questionMismatch();
        }

        if (Answer::query()->where('game_session_id', $locked->id)->where('question_id', $current->id)->exists()) {
            throw GameException::alreadyAnswered();
        }

        $question = $this->questions->get((int) $current->id)
            ?? throw GameException::questionMismatch();

        $score = $this->scores->score(
            $data->guess,
            $data->year,
            new Coordinates((float) $question->latitude, (float) $question->longitude),
            (int) $question->answer_year,
        );

        $answer = Answer::create([
            'game_session_id' => $locked->id,
            'question_id' => $question->id,
            'idempotency_key' => $data->idempotencyKey,
            'guessed_latitude' => $data->guess->latitude,
            'guessed_longitude' => $data->guess->longitude,
            'guessed_year' => $data->year,
            'distance_meters' => $score->distanceMeters,
            'year_difference' => $score->yearDifference,
            'geo_score' => $score->geoScore,
            'year_score' => $score->yearScore,
            'total_score' => $score->total(),
            'duration_ms' => $data->durationMs,
            'answered_at' => now(),
        ]);

        $locked->increment('total_score', $score->total());

        return $answer;
    }

    private function findByKey(GameSession $session, string $key): ?Answer
    {
        return Answer::query()
            ->where('game_session_id', $session->id)
            ->where('idempotency_key', $key)
            ->first();
    }
}
