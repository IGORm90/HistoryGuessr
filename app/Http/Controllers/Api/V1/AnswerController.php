<?php

namespace App\Http\Controllers\Api\V1;

use App\Actions\Game\SubmitAnswer;
use App\Http\Controllers\Api\V1\Concerns\ResolvesPlayerGame;
use App\Http\Requests\Api\V1\SubmitAnswerRequest;
use App\Http\Resources\Api\V1\AnswerRevealResource;
use App\Services\QuestionCache;

class AnswerController
{
    use ResolvesPlayerGame;

    public function store(SubmitAnswerRequest $request, string $gameSession, SubmitAnswer $submitAnswer, QuestionCache $questions): AnswerRevealResource
    {
        $answer = $submitAnswer->handle($this->playerGame($request, $gameSession), $request->toData());

        return new AnswerRevealResource($answer, $questions->get($answer->question_id) ?? $answer->question);
    }
}
