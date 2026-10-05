<?php

namespace App\DTO\Game;

final readonly class SubmitAnswerData
{
    public function __construct(
        public string $questionPublicId,
        public Coordinates $guess,
        public int $year,
        public string $idempotencyKey,
        public ?int $durationMs,
    ) {}
}
