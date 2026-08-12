<?php

namespace App\Enums;

enum OrganizationHierarchyLevel: int
{
    case GROUP_MANAGER = 1;
    case MANAGER = 2;
    case SUPERVISOR = 3;
    case STAFF = 4;
    case OPERATOR = 5;

    public static function fromCode(?string $code): ?self
    {
        return match (strtoupper(trim((string) $code))) {
            'GM' => self::GROUP_MANAGER,
            'MGR', 'COOR' => self::MANAGER,
            'SPV', 'SCH' => self::SUPERVISOR,
            'LDR', 'SN STF', 'STF' => self::STAFF,
            'OP' => self::OPERATOR,
            default => null,
        };
    }

    public function isAbove(self $other): bool
    {
        return $this->value < $other->value;
    }
}
