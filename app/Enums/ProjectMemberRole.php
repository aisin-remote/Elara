<?php

namespace App\Enums;

enum ProjectMemberRole: string
{
    case MANAGER = 'manager';
    case MEMBER = 'member';
    case VIEWER = 'viewer';

    public function label(): string
    {
        return $this === self::MANAGER ? 'Leader' : ucfirst($this->value);
    }
}
