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
            self::IN_PROGRESS => 'In Progress',
            default => ucfirst($this->value),
        };
    }
}
