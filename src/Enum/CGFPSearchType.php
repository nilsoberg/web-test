<?php

namespace App\Enum;

enum CGFPSearchType: string
{
    case USEARCH = 'usearch';
    case Diamond = 'diamond';

    public function getLabel(): string
    {
        return match($this) {
            self::USEARCH => 'USEARCH',
            self::Diamond => 'DIAMOND',
        };
    }
}
