<?php

namespace App\Enums;

enum ProjectType: string
{
    /** Delivery work with an end: what the Projects menu shows. */
    case PROJECT = 'project';

    /** A standing system that receives feature requests: what the Feature menu shows. */
    case SYSTEM = 'system';

    public function label(): string
    {
        return match ($this) {
            self::PROJECT => 'Project',
            self::SYSTEM => 'System',
        };
    }
}
