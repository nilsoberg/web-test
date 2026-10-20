<?php

namespace App\Dto;

final readonly class QueuedEmailContent
{
    public function __construct(
        public string $recipientEmail,
        public string $subject,
        public array $context,
    )
    {
    }
}
