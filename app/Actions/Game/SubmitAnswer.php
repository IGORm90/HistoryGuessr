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
     * a retry returns the answer created by the first request. After the round's deadline (plus grace)
     * new answers are refused with `round_expired`. When the client's countdown hits zero it sends
     * `timed_out` with whatever was picked: a point is scored as usual, no point is worth zero.
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

        $current = $locked->currentRound();

        if ($current === null || $current->questionPublicId !== $data->questionPublicId) {
            throw GameException::questionMismatch();
        }

        if (Answer::query()->where('game_session_id', $locked->id)->where('question_id', $current->questionId)->exists()) {
            throw GameException::alreadyAnswered();
        }

        $now = now()->toImmutable();

        // Checked after the idempotent replay above, so a retry of an answer accepted in time still succeeds.
        if ($current->isExpired($now)) {
            throw GameException::roundExpired();
        }

        $attributes = [
            'game_session_id' => $locked->id,
            'question_id' => $current->questionId,
            'idempotency_key' => $data->idempotencyKey,
            'timed_out' => $data->timedOut,
            'geo_score' => 0,
            'year_score' => 0,
            'total_score' => 0,
            // Measured on the server; null for rounds started before the timer existed.
            'duration_ms' => match (true) {
                $data->timedOut => $locked->round_time_limit_seconds * 1000,
                $current->startedAt !== null => max(0, $now->getTimestampMs() - $current->startedAt->getTimestampMs()),
                default => null,
            },
            'answered_at' => $now,
        ];

        // A timeout without a picked point is worth nothing; otherwise the picked guess is scored as usual.
        if ($data->guess !== null && $data->year !== null) {
            $question = $this->questions->get($current->questionId)
                ?? throw GameException::questionMismatch();

            $score = $this->scores->score(
                $data->guess,
                $data->year,
                new Coordinates((float) $question->latitude, (float) $question->longitude),
                (int) $question->answer_year,
            );

            $attributes = [
                ...$attributes,
                'guessed_latitude' => $data->guess->latitude,
                'guessed_longitude' => $data->guess->longitude,
                'guessed_year' => $data->year,
                'distance_meters' => $score->distanceMeters,
                'year_difference' => $score->yearDifference,
                'geo_score' => $score->geoScore,
                'year_score' => $score->yearScore,
                'total_score' => $score->total(),
            ];
        }

        $answer = Answer::create($attributes);

        if ($answer->total_score > 0) {
            $locked->increment('total_score', $answer->total_score);
        }

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
