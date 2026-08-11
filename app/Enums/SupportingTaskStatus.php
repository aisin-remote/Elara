<?php

namespace App\Enums;

enum SupportingTaskStatus: string
{
    case TODO = 'todo';
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::TODO => 'To Do',
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::TODO => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200',
            self::IN_PROGRESS => 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
            self::COMPLETED => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
            self::CANCELLED => 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
        };
    }
}
