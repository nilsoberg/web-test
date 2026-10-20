<?php

namespace App\Email;

final class EmailEventTag
{
    public const SUBMITTED = 'email_submitted';
    public const STARTED = 'email_started';
    public const FINISHED = 'email_finished';
    public const FAILED = 'email_failed';
    public const GENERIC = 'email_generic';
}
