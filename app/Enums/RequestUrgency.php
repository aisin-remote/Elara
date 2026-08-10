<?php

namespace App\Enums;

enum RequestUrgency: string
{
    case LOW = 'low';
    case NORMAL = 'normal';
    case HIGH = 'high';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Sorts the approvals queue. It does not jump the scheduling queue (PRD-05). */
    public function weight(): int
    {
        return match ($this) {
            self::HIGH => 3,
            self::NORMAL => 2,
            self::LOW => 1,
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::HIGH => 'danger',
            self::NORMAL => 'slate',
            self::LOW => 'slate',
        };
    }
}
