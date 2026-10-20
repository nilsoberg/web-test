<?php

namespace App\Enum;

enum AllByAllSearchSensitivity: string
{
    case Normal = 'normal';
    case Sensitive = 'sensitive';
    case MoreSensitive = 'more_sensitive';
    case VerySensitive = 'very_sensitive';
    case UltraSensitive = 'ultra_sensitive';

    public function getLabel(): string
    {
        return match($this) {
            self::Normal => 'Normal',
            self::Sensitive => 'Sensitive',
            self::MoreSensitive => 'More sensitive',
            self::VerySensitive => 'Very sensitive',
            self::UltraSensitive => 'Ultra sensitive',
        };
    }
}
