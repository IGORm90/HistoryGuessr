<?php

namespace App\Exceptions;

use RuntimeException;

class PublicationException extends RuntimeException
{
    /**
     * @param  list<string>  $problems  Human-readable reasons, shown to editors.
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('Нельзя опубликовать: '.implode('; ', $problems));
    }
}
