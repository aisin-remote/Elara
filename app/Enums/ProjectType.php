<?php

namespace App\Enums;

enum ProjectType: string
{
    /** Delivery work with an end: what the Projects menu shows. */
    case PROJECT = 'project';

    /** A standing system that receives feature requests: what the Feature menu shows. */
    case SYSTEM = 'system';

    /** Private task storage owned by one user; never shown as a project or system. */
    case PERSONAL = 'personal';

    public function label(): string
    {
        return match ($this) {
            self::PROJECT => 'Project',
            self::SYSTEM => 'System',
            self::PERSONAL => 'Personal',
        };
    }
}
