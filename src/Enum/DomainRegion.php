<?php

namespace App\Enum;

enum DomainRegion: string {
    case NTerminal = 'n-terminal';
    case Domain = 'domain';
    case CTerminal = 'c-terminal';
}
