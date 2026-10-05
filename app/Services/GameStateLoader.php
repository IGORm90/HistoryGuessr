<?php

namespace App\Services;

use App\Models\Answer;
use App\Models\GameSession;
use Illuminate\Support\Facades\DB;

class GameStateLoader
{
    public function __construct(private readonly QuestionCache $questions) {}

    public function load(GameSession $session): GameState
    {
        if (! $session->isInProgress()) {
            return new GameState($session, null, null);
        }

        $questionId = DB::table('game_session_questions')
            ->where('game_session_id', $session->id)
            ->where('position', $session->current_position)
            ->value('question_id');

        if ($questionId === null) {
            return new GameState($session, null, null);
        }

        $answer = Answer::query()
            ->where('game_session_id', $session->id)
            ->where('question_id', $questionId)
            ->first();

        return new GameState($session, $this->questions->get((int) $questionId), $answer);
    }
}
