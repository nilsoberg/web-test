<?php

namespace App\Exception;

class PublicUserCreationException extends \RuntimeException
{
    public function __construct(
        string $message = "Invalid user information",
        int $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
