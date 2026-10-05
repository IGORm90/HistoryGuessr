<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A game rule violation with a stable machine-readable code for the frontend.
 */
class GameException extends HttpException
{
    public function __construct(
        public readonly string $errorCode,
        string $message,
        int $status = 409,
    ) {
        parent::__construct($status, $message);
    }

    public static function quizUnavailable(): self
    {
        return new self('quiz_unavailable', 'В этой подборке пока нет опубликованных вопросов.');
    }

    public static function notInProgress(): self
    {
        return new self('game_not_in_progress', 'Игра уже завершена.');
    }

    public static function questionMismatch(): self
    {
        return new self('question_mismatch', 'Этот вопрос сейчас не активен.');
    }

    public static function alreadyAnswered(): self
    {
        return new self('already_answered', 'На этот вопрос уже дан ответ.');
    }

    public static function answerRequired(): self
    {
        return new self('answer_required', 'Сначала ответьте на текущий вопрос.');
    }

    public static function notCompleted(): self
    {
        return new self('game_not_completed', 'Итог доступен после завершения раунда.');
    }

    public static function sessionRequired(): self
    {
        return new self('session_required', 'Для игры нужна cookie-сессия браузера.', 400);
    }
}
