<?php

namespace App\Enums;

enum SystemPlant: string
{
    case BODY = 'body';
    case UNIT = 'unit';
    case ELECTRIC = 'electric';
    case OFFICE = 'office';

    public function label(): string
    {
        return match ($this) {
            self::BODY => 'Body',
            self::UNIT => 'Unit',
            self::ELECTRIC => 'Electric',
            self::OFFICE => 'Office',
        };
    }
}
