<?php

namespace App\Enum;

enum JobFormHandlerStatus
{
    case Success;
    case Error;
    case NotSubmitted;
}
