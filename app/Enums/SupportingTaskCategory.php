<?php

namespace App\Enums;

enum SupportingTaskCategory: string
{
    case HARDWARE = 'hardware';
    case SOFTWARE = 'software';
    case NETWORK = 'network';
    case OTHER = 'other';

    public function label(): string
    {
        return match ($this) {
            self::HARDWARE => 'Hardware / device',
            self::SOFTWARE => 'Software / account',
            self::NETWORK => 'Network',
            self::OTHER => 'Other',
        };
    }
}
