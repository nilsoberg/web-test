<?php

namespace App\Message;

final readonly class SubmissionEmailMessage
{
    public function __construct(
        public string $recipientEmail,
        public string $subject,
        public array $context,
        public string $messagePurpose,
    )
    {
    }
}

