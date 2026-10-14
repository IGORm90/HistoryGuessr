<?php

namespace App\Services;

use App\Actions\Game\ExpireRound;
use App\Models\Answer;
use App\Models\GameSession;

class GameStateLoader
{
    public const string NOTICE_PREVIOUS_ROUND_EXPIRED = 'previous_round_expired';

    public function __construct(
        private readonly QuestionCache $questions,
        private readonly ExpireRound $expire,
    ) {}

    /**
     * A round that ran out of time while the player was away is skipped here (no scheduler needed):
     * it scores zero, the next question starts with a fresh timer (or the game completes), and this
     * response alone carries a notice about it. The skipped question's answer is never revealed.
     */
    public function load(GameSession $session): GameState
    {
        if (! $session->isInProgress()) {
            return new GameState($session, null, null);
        }

        $round = $session->currentRound();

        if ($round === null) {
            return new GameState($session, null, null);
        }

        $answer = Answer::query()
            ->where('game_session_id', $session->id)
            ->where('question_id', $round->questionId)
            ->first();

        // Cheap timestamp check first; the locking path runs only for an actually expired round.
        if ($answer === null && $round->isExpired(now()->toImmutable()) && $this->expire->handle($session)) {
            $session->refresh();
            $state = $this->load($session);

            return new GameState($state->session, $state->question, $state->answer, $state->round, self::NOTICE_PREVIOUS_ROUND_EXPIRED);
        }

        return new GameState($session, $this->questions->get($round->questionId), $answer, $round);
    }
}
