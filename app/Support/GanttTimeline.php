<?php

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Window + geometry for a horizontal timeline: where a bar sits (percent from the
 * left, percent wide) and where the gridlines and "today" marker fall.
 */
class GanttTimeline
{
    public const SCALES = ['daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly', 'yearly' => 'Yearly'];

    public readonly string $scale;

    public readonly CarbonImmutable $from;

    public readonly CarbonImmutable $to;

    private readonly int $seconds;

    private readonly CarbonImmutable $today;

    public function __construct(?string $scale, private readonly string $timezone)
    {
        $this->scale = array_key_exists((string) $scale, self::SCALES) ? (string) $scale : 'weekly';
        $this->today = CarbonImmutable::now($this->timezone);

        [$this->from, $this->to] = match ($this->scale) {
            'daily' => [$this->today->startOfDay()->subDays(3), $this->today->endOfDay()->addDays(10)],
            'monthly' => [$this->today->startOfMonth()->subMonthsNoOverflow(1), $this->today->endOfMonth()->addMonthsNoOverflow(5)],
            'yearly' => [$this->today->startOfYear(), $this->today->endOfYear()->addYears(2)],
            default => [$this->today->startOfWeek()->subWeeks(1), $this->today->endOfWeek()->addWeeks(7)],
        };

        $this->seconds = max(1, $this->from->diffInSeconds($this->to));
    }

    /** Null when the bar falls entirely outside the visible window. */
    public function bar(CarbonImmutable $start, CarbonImmutable $end): ?array
    {
        if ($end->lt($this->from) || $start->gt($this->to)) {
            return null;
        }

        $left = $this->position($start->max($this->from));
        $width = max(1.25, $this->position($end->min($this->to)) - $left);

        return ['left' => $left, 'width' => min(100 - $left, $width)];
    }

    public function ticks(): array
    {
        $ticks = [];
        $cursor = $this->from;

        while ($cursor->lte($this->to)) {
            $ticks[] = [
                'label' => match ($this->scale) {
                    'daily' => $cursor->format('D, M j'),
                    'monthly' => $cursor->format('M Y'),
                    'yearly' => $cursor->format('Y'),
                    default => $cursor->format('M j'),
                },
                'left' => $this->position($cursor),
            ];

            $cursor = match ($this->scale) {
                'daily' => $cursor->addDay(),
                'monthly' => $cursor->addMonthNoOverflow(),
                'yearly' => $cursor->addYear(),
                default => $cursor->addWeek(),
            };
        }

        return $ticks;
    }

    public function todayPosition(): ?float
    {
        return $this->today->betweenIncluded($this->from, $this->to) ? $this->position($this->today) : null;
    }

    public function minWidth(): int
    {
        return match ($this->scale) {
            'daily' => 1080,
            'monthly' => 900,
            'yearly' => 760,
            default => 960,
        };
    }

    private function position(CarbonImmutable $moment): float
    {
        return round($this->from->diffInSeconds($moment) / $this->seconds * 100, 3);
    }
}
