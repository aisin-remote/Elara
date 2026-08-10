<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case PLANNED = 'planned';
    case ACTIVE = 'active';
    case ON_HOLD = 'on_hold';
    case COMPLETED = 'completed';
    case ARCHIVED = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::PLANNED => 'Planned',
            self::ACTIVE => 'Active',
            self::ON_HOLD => 'On hold',
            self::COMPLETED => 'Completed',
            self::ARCHIVED => 'Archived',
        };
    }
}
