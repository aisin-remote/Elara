<?php

namespace App\Enums;

enum MeetingMinuteRelation: string
{
    case GENERAL = 'general';
    case PROJECT = 'project';
    case FEATURE = 'feature';

    public function label(): string
    {
        return match ($this) {
            self::GENERAL => 'General',
            self::PROJECT => 'Project',
            self::FEATURE => 'Feature',
        };
    }
}
