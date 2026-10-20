<?php

namespace App\Services\Sources;

class SourceException extends \RuntimeException
{
    public function __construct(string $message, public int $retryAfter = 0)
    {
        parent::__construct($message);
    }
}
