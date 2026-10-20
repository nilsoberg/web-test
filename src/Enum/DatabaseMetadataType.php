<?php

namespace App\Enum;

enum DatabaseMetadataType: string
{
    case EST = 'est';
    case Metagenome = 'metagenome';

    public function getLabel(): string
    {
        return match($this) {
            self::EST => 'EST',
            self::Metagenome => 'Metagenome',
        };
    }
}
