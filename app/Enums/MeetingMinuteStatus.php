<?php

namespace App\Enums;

enum MeetingMinuteStatus: string
{
    case OUTSTANDING = 'outstanding';
    case IN_PROGRESS = 'in_progress';
    case PENDING = 'pending';
    case DONE = 'done';

    public function label(): string
    {
        return match ($this) {
            self::OUTSTANDING => 'Outstanding',
            self::IN_PROGRESS => 'In progress',
            self::PENDING => 'Pending',
            self::DONE => 'Done',
        };
    }
}
