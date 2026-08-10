<?php

namespace App\Enums;

enum DependencyType: string
{
    case FINISH_TO_START = 'fs';
    case START_TO_START = 'ss';
    case FINISH_TO_FINISH = 'ff';
    case START_TO_FINISH = 'sf';

    public function label(): string
    {
        return match ($this) {
            self::FINISH_TO_START => 'Finish to start',
            self::START_TO_START => 'Start to start',
            self::FINISH_TO_FINISH => 'Finish to finish',
            self::START_TO_FINISH => 'Start to finish',
        };
    }

    /** Whether an unfinished/unstarted prerequisite should mark the dependent blocked. */
    public function blocksStart(): bool
    {
        return match ($this) {
            self::FINISH_TO_START, self::START_TO_START => true,
            self::FINISH_TO_FINISH, self::START_TO_FINISH => false,
        };
    }
}
