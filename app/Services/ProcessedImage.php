<?php

namespace App\Services;

final readonly class ProcessedImage
{
    public function __construct(
        public string $largePath,
        public string $mediumPath,
        public int $width,
        public int $height,
    ) {}
}
