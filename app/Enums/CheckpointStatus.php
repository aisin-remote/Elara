<?php

namespace App\Enums;

enum CheckpointStatus: string
{
    case OPEN = 'open';
    case APPROVED = 'approved';
    case CHANGES_REQUESTED = 'changes_requested';
    case EXPIRED = 'expired';
    case CANCELLED = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::OPEN => 'Waiting for you',
            self::APPROVED => 'Approved',
            self::CHANGES_REQUESTED => 'Changes requested',
            self::EXPIRED => 'Expired',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::OPEN => 'warning',
            self::APPROVED => 'success',
            self::CHANGES_REQUESTED => 'info',
            self::EXPIRED => 'danger',
            self::CANCELLED => 'neutral',
        };
    }

    /** Only an open checkpoint is still counting down. */
    public function isCountingDown(): bool
    {
        return $this === self::OPEN;
    }
}
