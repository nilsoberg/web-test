<?php

namespace App\Enum;

enum JobFormSubmissionHandlerType
{
    case Basic;
    case None;
    case StringOrFile;
    case StringToFile;
    case Uploaded;
}
