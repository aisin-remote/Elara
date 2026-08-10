<?php

namespace Tests\Unit;

use App\Support\GanttTimeline;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class GanttTimelineTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Thursday: the weekly window runs Mon Jul 20 → Sun Sep 20 (10 weeks).
        $this->travelTo(CarbonImmutable::parse('2026-07-30 00:00:00', 'UTC'));
    }

    public function test_weekly_window_brackets_today_and_places_the_marker(): void
    {
        $timeline = new GanttTimeline(null, 'UTC');

        $this->assertSame('weekly', $timeline->scale);
        $this->assertSame('2026-07-20', $timeline->from->format('Y-m-d'));
        $this->assertSame('2026-09-20', $timeline->to->format('Y-m-d'));
        $this->assertEqualsWithDelta(15.87, $timeline->todayPosition(), 0.5);
    }

    public function test_bar_is_clipped_to_the_window_and_dropped_when_outside_it(): void
    {
        $timeline = new GanttTimeline('weekly', 'UTC');

        $inside = $timeline->bar(
            CarbonImmutable::parse('2026-08-03 00:00:00', 'UTC'),
            CarbonImmutable::parse('2026-08-09 23:59:59', 'UTC'),
        );
        $this->assertEqualsWithDelta(22.6, $inside['left'], 0.5);
        $this->assertEqualsWithDelta(11.3, $inside['width'], 0.5);

        $straddling = $timeline->bar(
            CarbonImmutable::parse('2026-06-01 00:00:00', 'UTC'),
            CarbonImmutable::parse('2026-07-27 23:59:59', 'UTC'),
        );
        $this->assertSame(0.0, $straddling['left']);
        $this->assertLessThan(100, $straddling['width']);

        $this->assertNull($timeline->bar(
            CarbonImmutable::parse('2026-01-05 00:00:00', 'UTC'),
            CarbonImmutable::parse('2026-01-09 23:59:59', 'UTC'),
        ));
    }

    public function test_unknown_scale_falls_back_and_ticks_cover_the_window(): void
    {
        $timeline = new GanttTimeline('fortnightly', 'UTC');
        $ticks = $timeline->ticks();

        $this->assertSame('weekly', $timeline->scale);
        $this->assertCount(9, $ticks);
        $this->assertSame(0.0, $ticks[0]['left']);
        $this->assertLessThanOrEqual(100, end($ticks)['left']);
    }
}
