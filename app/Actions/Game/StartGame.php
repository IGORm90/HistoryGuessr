<?php

namespace App\Actions\Game;

use App\Enums\GameSessionStatus;
use App\Exceptions\GameException;
use App\Models\GameSession;
use App\Models\Quiz;
use App\Support\RoundClock;
use App\Support\WeightedSampler;
use Illuminate\Support\Facades\DB;

class StartGame
{
    public function __construct(private readonly WeightedSampler $sampler) {}

    /**
     * Picks the round's questions by weight among the quiz's published questions and fixes their order.
     * If editors unpublished questions so fewer than `round_size` remain, the round is shorter.
     */
    public function handle(Quiz $quiz, string $anonymousId): GameSession
    {
        /** @var array<int, int> $weights question id => weight */
        $weights = DB::table('quiz_questions')
            ->join('questions', 'questions.id', '=', 'quiz_questions.question_id')
            ->where('quiz_questions.quiz_id', $quiz->id)
            ->where('questions.status', 'published')
            ->pluck('quiz_questions.weight', 'quiz_questions.question_id')
            ->map(fn ($weight) => (int) $weight)
            ->all();

        if ($weights === []) {
            throw GameException::quizUnavailable();
        }

        $questionIds = $this->sampler->sample($weights, $quiz->round_size);

        return DB::transaction(function () use ($quiz, $anonymousId, $questionIds): GameSession {
            $startedAt = now()->toImmutable();
            $limit = RoundClock::limitSeconds();

            $session = GameSession::create([
                'quiz_id' => $quiz->id,
                'anonymous_id' => $anonymousId,
                'status' => GameSessionStatus::InProgress,
                'current_position' => 1,
                'question_count' => count($questionIds),
                'total_score' => 0,
                'round_time_limit_seconds' => $limit,
                'started_at' => $startedAt,
            ]);

            // The first question is shown right away, so its round starts with the game.
            DB::table('game_session_questions')->insert(array_map(
                fn (int $questionId, int $index) => [
                    'game_session_id' => $session->id,
                    'question_id' => $questionId,
                    'position' => $index + 1,
                    'started_at' => $index === 0 ? RoundClock::toDatabase($startedAt) : null,
                    'deadline_at' => $index === 0 ? RoundClock::toDatabase(RoundClock::deadline($startedAt, $limit)) : null,
                ],
                $questionIds,
                array_keys($questionIds),
            ));

            return $session;
        });
    }
}
