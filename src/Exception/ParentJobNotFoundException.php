<?php

namespace App\Exception;

class ParentJobNotFoundException extends \RuntimeException
{
    public function __construct(
        string $message = "The specified parent job could not be found.",
        int $code = 0,
        ?\Throwable $previous = null
    )
    {
        parent::__construct($message, $code, $previous);
    }
}
