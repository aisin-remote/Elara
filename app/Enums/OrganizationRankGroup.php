<?php

namespace App\Enums;

enum OrganizationRankGroup: string
{
    case MANAGEMENT = 'management';
    case SUPERVISION = 'supervision';
    case STAFF = 'staff';

    public static function fromCode(?string $code): ?self
    {
        return match (strtoupper(trim((string) $code))) {
            'GM', 'MGR', 'COOR' => self::MANAGEMENT,
            'SPV', 'SCH' => self::SUPERVISION,
            'LDR', 'STF', 'SN STF', 'OP' => self::STAFF,
            default => null,
        };
    }

    /** @return array<int, string> */
    public static function managementCodes(): array
    {
        return ['MGR', 'COOR'];
    }
}
