<?php

namespace App\Enums;

enum WorkspaceRole: string
{
    case OWNER = 'owner';
    case ADMIN = 'admin';
    case MANAGER = 'manager';
    case SUPERVISOR = 'supervisor';
    case MEMBER = 'member';
    case VIEWER = 'viewer';
    case REQUESTER = 'requester';

    public function label(): string
    {
        return match ($this) {
            self::OWNER => 'Owner',
            self::ADMIN => 'Admin',
            self::MANAGER => 'Manager',
            self::SUPERVISOR => 'Supervisor',
            self::MEMBER => 'Member',
            self::VIEWER => 'Viewer',
            self::REQUESTER => 'Requester',
        };
    }

    /**
     * Roles an admin may hand out, keyed by value. Owner is absent: it moves only through
     * ownership transfer. One source for the invite form, the member form, and validation.
     *
     * @return array<string, string>
     */
    public static function assignable(): array
    {
        return collect(self::cases())
            ->reject(fn (self $role) => $role === self::OWNER)
            ->mapWithKeys(fn (self $role) => [$role->value => $role->label()])
            ->all();
    }

    /**
     * May open the IT delivery desk at all. A requester submits work and validates it;
     * every other role works on it.
     */
    public function canAccessDeliveryDesk(): bool
    {
        return $this !== self::REQUESTER;
    }

    /**
     * The exact mirror of canAccessDeliveryDesk, and deliberately not a second opinion: one
     * role belongs to one side. Someone on the delivery team who needs to raise a request is
     * given a requester membership, rather than the rule growing an exception.
     */
    public function canUseRequestDesk(): bool
    {
        return $this === self::REQUESTER;
    }

    /**
     * May create or change delivery content: tasks, files, conversations, schedule events.
     * Policies must ask this instead of testing "is not a viewer" — a deny-list silently
     * grants every future role, which is how a requester would have gained write access.
     */
    public function canContribute(): bool
    {
        return match ($this) {
            self::OWNER, self::ADMIN, self::MANAGER, self::SUPERVISOR, self::MEMBER => true,
            self::VIEWER, self::REQUESTER => false,
        };
    }
}
