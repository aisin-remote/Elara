<?php

namespace App\Enums;

enum BreakdownStatus: string
{
    case PENDING = 'pending';
    case READY = 'ready';
    case ACCEPTED = 'accepted';
    case FAILED = 'failed';
    // Beyond the four PRD-06 sketched. A discarded draft is not a failure — the provider did
    // its job and a human said no — and it still has to stay auditable, so it is not deleted.
    case DISCARDED = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Generating',
            self::READY => 'Ready for review',
            self::ACCEPTED => 'Accepted',
            self::FAILED => 'Failed',
            self::DISCARDED => 'Discarded',
        };
    }

    /** A breakdown that already exists in one of these states must not be regenerated blindly. */
    public function isSettled(): bool
    {
        return $this === self::READY || $this === self::ACCEPTED;
    }
}
