<?php

namespace App\Enums;

enum FeatureRequestStatus: string
{
    case DRAFT = 'draft';
    case PENDING_DEPARTMENT = 'pending_department';
    case PENDING_REVIEW = 'pending_review';
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
            self::PENDING_REVIEW => 'Under review',
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
     * The only transitions the system permits. Anything not listed here is refused in the
     * Action, so a controller bug cannot walk a request into a state nobody designed.
     *
     * @return array<int, self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::DRAFT => [self::PENDING_DEPARTMENT, self::PENDING_REVIEW],
            self::PENDING_DEPARTMENT => [self::PENDING_REVIEW, self::REJECTED, self::NEEDS_INFO],
            self::PENDING_REVIEW => [self::APPROVED, self::REJECTED, self::NEEDS_INFO],
            self::NEEDS_INFO => [self::PENDING_DEPARTMENT, self::PENDING_REVIEW, self::REJECTED],
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

    /** Waiting on someone: shown in the approvals queue. */
    public function isAwaitingReview(): bool
    {
        return $this === self::PENDING_REVIEW;
    }

    public function isAwaitingDepartment(): bool
    {
        return $this === self::PENDING_DEPARTMENT;
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::DELIVERED, self::REJECTED, self::TAKEN_DOWN], true);
    }

    public function tone(): string
    {
        return match ($this) {
            self::DELIVERED => 'success',
            self::REJECTED, self::TAKEN_DOWN => 'danger',
            self::NEEDS_INFO, self::PENDING_DEPARTMENT => 'warning',
            default => 'slate',
        };
    }
}
