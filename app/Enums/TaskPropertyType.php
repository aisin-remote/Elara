<?php

namespace App\Enums;

enum TaskPropertyType: string
{
    case TEXT = 'text';
    case SELECT = 'select';
    case CHECKBOX = 'checkbox';

    public function label(): string
    {
        return match ($this) {
            self::TEXT => 'Text',
            self::SELECT => 'Select',
            self::CHECKBOX => 'Checklist',
        };
    }
}
