<?php

namespace App\Enum;

enum SequenceType: string {
    case UniProt = 'uniprot';
    case UniRef90 = 'uniref90';
    case UniRef50 = 'uniref50';

    public function getLabel(): string
    {
        return match($this) {
            self::UniProt => 'UniProt',
            self::UniRef90 => 'UniRef90',
            self::UniRef50 => 'UniRef50',
        };
    }
}
