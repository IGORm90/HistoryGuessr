<?php

namespace App\DTO\Game;

final readonly class SubmitAnswerData
{
    /**
     * @param  Coordinates|null  $guess  null only for a timeout without a picked point
     * @param  int|null  $year  null exactly when `$guess` is null
     * @param  bool  $timedOut  sent by the client when its countdown hit zero
     */
    public function __construct(
        public string $questionPublicId,
        public ?Coordinates $guess,
        public ?int $year,
        public string $idempotencyKey,
        public bool $timedOut = false,
    ) {}
}
