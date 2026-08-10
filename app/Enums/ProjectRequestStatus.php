<?php

namespace App\Enums;

enum ProjectRequestStatus: string
{
    case DRAFT = 'draft';
    case PENDING_DEPARTMENT = 'pending_department';
    case PENDING_MEETING = 'pending_meeting';
    case PENDING_SPV = 'pending_spv';
    case PENDING_MANAGER = 'pending_manager';
    case NEEDS_INFO = 'needs_info';
    case APPROVED = 'approved';
    case SCHEDULED = 'scheduled';
    case IN_PROGRESS = 'in_progress';
    case DELIVERED = 'delivered';
    case REJECTED = 'rejected';
    case TAKEN_DOWN = 'taken_down';

    public function label(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::PENDING_DEPARTMENT => 'Menunggu approval department',
            self::PENDING_MEETING => 'Awaiting scoping meeting',
            // Both are still waiting, so neither may say "approved by": at PENDING_SPV nobody
            // has signed at all, and at PENDING_MANAGER the supervisor's signature is only
            // half of what this needs. A label that claims approval invites the second
            // signatory to assume the decision is already made.
            self::PENDING_SPV => 'Needs supervisor approval',
            self::PENDING_MANAGER => 'Needs manager approval',
            self::NEEDS_INFO => 'Needs your input',
            self::APPROVED => 'Approved',
            self::SCHEDULED => 'Scheduled',
            self::IN_PROGRESS => 'In progress',
            self::DELIVERED => 'Delivered',
            self::REJECTED => 'Rejected',
            self::TAKEN_DOWN => 'Taken down',
        };
    }

    /**
     * Approval is sequential on purpose: a manager signing before the supervisor has read it
     * defeats the point of a first-line filter.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_DEPARTMENT, self::PENDING_MEETING],
            self::PENDING_DEPARTMENT => [self::PENDING_MEETING, self::REJECTED, self::NEEDS_INFO],
            self::PENDING_MEETING => [self::PENDING_SPV, self::REJECTED],
            self::PENDING_SPV => [self::PENDING_MANAGER, self::REJECTED, self::NEEDS_INFO],
            self::PENDING_MANAGER => [self::APPROVED, self::REJECTED, self::NEEDS_INFO],
            self::NEEDS_INFO => [self::PENDING_DEPARTMENT, self::PENDING_SPV, self::REJECTED],
            self::APPROVED => [self::SCHEDULED, self::TAKEN_DOWN],
            self::SCHEDULED => [self::IN_PROGRESS, self::TAKEN_DOWN],
            self::IN_PROGRESS => [self::DELIVERED, self::TAKEN_DOWN],
            self::DELIVERED, self::REJECTED, self::TAKEN_DOWN => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /** Sitting in somebody's queue rather than in delivery. */
    public function isAwaitingDecision(): bool
    {
        return in_array($this, [self::PENDING_MEETING, self::PENDING_SPV, self::PENDING_MANAGER], true);
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::DELIVERED, self::REJECTED, self::TAKEN_DOWN], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::DELIVERED, self::APPROVED => 'success',
            self::REJECTED, self::TAKEN_DOWN => 'danger',
            self::NEEDS_INFO, self::PENDING_DEPARTMENT, self::PENDING_MEETING => 'warning',
            default => 'slate',
        };
    }
}
