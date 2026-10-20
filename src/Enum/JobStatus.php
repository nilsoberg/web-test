<?php

namespace App\Enum;

enum JobStatus: string {
    case New = 'new';
    case Running = 'running';
    case Finished = 'finished';
    case Failed = 'failed';
    case Archived = 'archived';
    case Cancelling = 'cancelling';
    case Cancelled = 'cancelled';
}
