<?php

namespace App\Enums;

enum ForecastState: string
{
    case COMPLETE = 'complete';
    case ON_TRACK = 'on_track';
    case AT_RISK = 'at_risk';
    case LATE = 'late';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETE => 'Complete',
            self::ON_TRACK => 'On track',
            self::AT_RISK => 'At risk',
            self::LATE => 'Late',
        };
    }
}
