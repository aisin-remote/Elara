<?php

namespace App\Enums;

enum TaskStatusCategory: string
{
    case BACKLOG = 'backlog';
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::BACKLOG => 'Pending',
            self::TODO => 'Outstanding',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Done',
            self::CANCELLED => 'Cancelled',
        };
    }
}
