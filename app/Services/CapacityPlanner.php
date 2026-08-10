<?php

namespace App\Services;

use App\Enums\BreakdownStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\ProjectMemberRole;
use App\Enums\ProjectRequestStatus;
use App\Models\CapacityException;
use App\Models\FeatureRequest;
use App\Models\MemberCapacity;
use App\Models\Project;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Availability is computed from committed effort per working day, not from calendar
 * free/busy. Someone with a full day of estimated task work is unavailable even with an
 * empty calendar; someone with two meetings still has room for four hours of work.
 * Meetings are subtracted as overhead, never treated as the source of truth.
 *
 * Deterministic by construction: same data in, same assignment out.
 */
class CapacityPlanner
{
    public function __construct(private readonly WorkspaceSettings $settings) {}

    /**
     * The earliest working day on which this person can begin a block of this size, and the
     * day the block finishes.
     *
     * @return array{start: CarbonImmutable, due: CarbonImmutable}|null Null when nothing
     *                                                                  opens inside the horizon.
     */
    public function availableFrom(Workspace $workspace, User $user, int $minutes): ?array
    {
        $minutes = max(1, $minutes);
        $capacity = $this->capacityFor($workspace, $user);
        $dailyMinutes = (int) round($capacity['hours_per_day'] * 60);

        if ($dailyMinutes <= 0) {
            return null;
        }

        $timezone = $this->timezone($workspace);
        $cursor = CarbonImmutable::now($timezone)->startOfDay();
        $horizon = $cursor->addDays($this->settings->horizonDays($workspace));
        $committed = $this->committedMinutesByDate($workspace, $user, $capacity, $dailyMinutes);
        $blocked = $this->blockedDates($workspace, $user);

        $start = null;
        $remaining = $minutes;

        while ($cursor->lte($horizon)) {
            $free = $this->freeMinutesOn($cursor, $capacity, $dailyMinutes, $committed, $blocked);

            // A day with nothing free is stepped over, not treated as a failure: weekends,
            // holidays, leave, and fully booked days all just do not contribute. Work spans
            // them rather than restarting after them.
            if ($free <= 0) {
                $cursor = $cursor->addDay();

                continue;
            }

            $start ??= $cursor;
            $remaining -= $free;

            if ($remaining <= 0) {
                return ['start' => $start, 'due' => $cursor];
            }

            $cursor = $cursor->addDay();
        }

        return null;
    }

    /**
     * Who gets the work and when. The system's PIC is preferred, but only within a grace
     * window: a queue behind one expert is how a delivery team acquires a bottleneck.
     *
     * @return array{user: User, start: CarbonImmutable, due: CarbonImmutable, pic_deferred: bool}|null
     */
    public function assign(Workspace $workspace, ?Project $system, int $minutes): ?array
    {
        $candidates = $this->candidates($workspace, $system);

        if ($candidates->isEmpty()) {
            return null;
        }

        $slots = [];

        foreach ($candidates as $index => $candidate) {
            $slot = $this->availableFrom($workspace, $candidate, $minutes);

            if ($slot !== null) {
                $slots[] = ['user' => $candidate, 'order' => $index, ...$slot];
            }
        }

        if ($slots === []) {
            return null;
        }

        // Earliest opening wins; ties break on candidate order, which is PIC first.
        usort($slots, fn (array $a, array $b) => [$a['start'], $a['order']] <=> [$b['start'], $b['order']]);
        $best = $slots[0];
        $pic = $slots[0]['order'] === 0 ? $slots[0] : null;

        foreach ($slots as $slot) {
            if ($slot['order'] === 0) {
                $pic = $slot;
                break;
            }
        }

        // The PIC keeps the work whenever their opening is inside the grace window.
        if ($pic !== null && $pic['start']->diffInDays($best['start'], false) >= -$this->settings->picGraceDays($workspace)) {
            $best = $pic;
        }

        return [
            'user' => $best['user'],
            'start' => $best['start'],
            'due' => $best['due'],
            'pic_deferred' => $best['order'] !== 0,
        ];
    }

    /**
     * Where a sequence of work blocks lands for one person, starting no earlier than $from.
     * Used when an accepted breakdown becomes tasks: laying them out with the same rule that
     * reserved the window means releasing the reservation and creating the tasks is
     * capacity-neutral rather than a jump in either direction.
     *
     * @param  array<int, int>  $minutes  Effort per block, in order.
     * @return array<int, CarbonImmutable> One due date per block, same keys.
     */
    public function layOut(
        Workspace $workspace,
        User $user,
        array $minutes,
        CarbonImmutable $from,
        FeatureRequest|ProjectRequest|null $ignoring = null,
    ): array {
        $capacity = $this->capacityFor($workspace, $user);
        $dailyMinutes = max(1, (int) round($capacity['hours_per_day'] * 60));
        $committed = $this->committedMinutesByDate($workspace, $user, $capacity, $dailyMinutes, $ignoring);
        $blocked = $this->blockedDates($workspace, $user);

        $cursor = $from->startOfDay();
        $horizon = $cursor->addDays($this->settings->horizonDays($workspace));
        $dates = [];

        foreach ($minutes as $index => $required) {
            $remaining = max(1, $required);

            while ($cursor->lte($horizon)) {
                $free = $this->freeMinutesOn($cursor, $capacity, $dailyMinutes, $committed, $blocked);

                if ($free <= 0) {
                    $cursor = $cursor->addDay();

                    continue;
                }

                $key = $cursor->format('Y-m-d');
                $take = min($remaining, $free);
                $committed[$key] = ($committed[$key] ?? 0) + $take;
                $remaining -= $take;

                if ($remaining <= 0) {
                    break;
                }

                $cursor = $cursor->addDay();
            }

            // Past the horizon everything piles on the last day rather than vanishing: a date
            // a human can see and argue with beats a null nobody notices.
            $dates[$index] = $cursor->lte($horizon) ? $cursor : $horizon;
        }

        return $dates;
    }

    /**
     * PIC first, then the system's other managers, then its members, then anyone in the
     * workspace who can contribute.
     *
     * @return Collection<int, User>
     */
    private function candidates(Workspace $workspace, ?Project $system)
    {
        $ordered = collect();

        if ($system) {
            // An explicit comparator, not sortBy([...]): Laravel calls closures passed that
            // way as two-argument comparators, which silently sorted the PIC last.
            $ordered = $system->memberships()
                ->with('user')
                ->get()
                ->sort(fn ($a, $b) => [$a->role === ProjectMemberRole::MANAGER ? 0 : 1, $a->id]
                    <=> [$b->role === ProjectMemberRole::MANAGER ? 0 : 1, $b->id])
                ->pluck('user')
                ->filter();
        }

        $fallback = $workspace->memberships()
            ->active()
            ->with('user')
            ->orderBy('id')
            ->get()
            ->filter(fn ($membership) => $membership->role->canContribute())
            ->pluck('user')
            ->filter();

        return $ordered->concat($fallback)->unique('id')->values();
    }

    private function freeMinutesOn(
        CarbonImmutable $date,
        array $capacity,
        int $dailyMinutes,
        array $committed,
        array $blocked,
    ): int {
        if (! in_array($date->dayOfWeekIso, $capacity['working_days'], true)) {
            return 0;
        }

        $key = $date->format('Y-m-d');

        if (isset($blocked[$key])) {
            return 0;
        }

        return max(0, $dailyMinutes - ($committed[$key] ?? 0));
    }

    /** @return array{hours_per_day: float, working_days: array<int, int>} */
    public function capacityFor(Workspace $workspace, User $user): array
    {
        $record = MemberCapacity::query()
            ->where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->whereDate('effective_from', '<=', now())
            ->orderByDesc('effective_from')
            ->first();

        return [
            'hours_per_day' => $record?->hours_per_day ?? MemberCapacity::DEFAULT_HOURS_PER_DAY,
            'working_days' => $record?->workingDays() ?? MemberCapacity::DEFAULT_WORKING_DAYS,
        ];
    }

    public function hoursPerDay(Workspace $workspace, User $user): float
    {
        return (float) $this->capacityFor($workspace, $user)['hours_per_day'];
    }

    /**
     * Earliest working window for $minutes that does not begin before $notBefore.
     *
     * @return array{start: CarbonImmutable, due: CarbonImmutable}|null
     */
    public function windowFrom(
        Workspace $workspace,
        User $user,
        int $minutes,
        CarbonImmutable $notBefore,
        FeatureRequest|ProjectRequest|null $ignoring = null,
    ): ?array {
        $minutes = max(1, $minutes);
        $capacity = $this->capacityFor($workspace, $user);
        $dailyMinutes = (int) round($capacity['hours_per_day'] * 60);

        if ($dailyMinutes <= 0) {
            return null;
        }

        $timezone = $this->timezone($workspace);
        $cursor = $notBefore->setTimezone($timezone)->startOfDay();
        $horizon = CarbonImmutable::now($timezone)->startOfDay()->addDays($this->settings->horizonDays($workspace));
        if ($cursor->gt($horizon)) {
            $horizon = $cursor->addDays($this->settings->horizonDays($workspace));
        }

        $committed = $this->committedMinutesByDate($workspace, $user, $capacity, $dailyMinutes, $ignoring);
        $blocked = $this->blockedDates($workspace, $user);

        $start = null;
        $remaining = $minutes;

        while ($cursor->lte($horizon)) {
            $free = $this->freeMinutesOn($cursor, $capacity, $dailyMinutes, $committed, $blocked);

            if ($free <= 0) {
                $cursor = $cursor->addDay();

                continue;
            }

            $start ??= $cursor;
            $key = $cursor->format('Y-m-d');
            $take = min($remaining, $free);
            $committed[$key] = ($committed[$key] ?? 0) + $take;
            $remaining -= $take;

            if ($remaining <= 0) {
                return ['start' => $start, 'due' => $cursor];
            }

            $cursor = $cursor->addDay();
        }

        return $start === null ? null : ['start' => $start, 'due' => $horizon];
    }

    /**
     * Effort already promised, plus meeting time, keyed by date.
     *
     * @param  array{hours_per_day: float, working_days: array<int, int>}  $capacity
     * @param  FeatureRequest|ProjectRequest|null  $ignoring  Whose reservation to leave out —
     *                                                        the request whose plan is being
     *                                                        previewed must not block its own days.
     * @return array<string, int>
     */
    private function committedMinutesByDate(
        Workspace $workspace,
        User $user,
        array $capacity,
        int $dailyMinutes,
        FeatureRequest|ProjectRequest|null $ignoring = null,
    ): array {
        $committed = [];

        Task::query()
            ->where('workspace_id', $workspace->id)
            ->whereNull('archived_at')
            ->whereNull('completed_at')
            ->whereNotNull('due_at')
            ->whereNotNull('estimate_minutes')
            ->whereHas('assignees', fn ($query) => $query->where('users.id', $user->id))
            ->get(['due_at', 'estimate_minutes'])
            ->each(function (Task $task) use (&$committed): void {
                $key = $task->due_at->format('Y-m-d');
                $committed[$key] = ($committed[$key] ?? 0) + (int) $task->estimate_minutes;
            });

        $workspace->scheduleEvents()
            ->whereHas('attendees', fn ($query) => $query->where('users.id', $user->id))
            ->where('start_at', '>=', now()->startOfDay())
            ->get(['start_at', 'end_at'])
            ->each(function ($event) use (&$committed): void {
                $key = $event->start_at->format('Y-m-d');
                $minutes = max(0, $event->start_at->diffInMinutes($event->end_at));
                $committed[$key] = ($committed[$key] ?? 0) + (int) $minutes;
            });

        // Work that is scheduled but not yet broken into tasks (PRD-06) still owns its days.
        // Without this the whole approved queue would drain onto the same free morning,
        // because a request only becomes visible effort once it has tasks.
        foreach ($this->reservations($workspace, $user, $ignoring) as $reservation) {
            $cursor = CarbonImmutable::parse($reservation['start'])->startOfDay();
            $end = CarbonImmutable::parse($reservation['due'])->startOfDay();
            $remaining = $reservation['minutes'];

            while ($remaining > 0 && $cursor->lte($end)) {
                if (in_array($cursor->dayOfWeekIso, $capacity['working_days'], true)) {
                    $key = $cursor->format('Y-m-d');
                    $take = min($remaining, max(0, $dailyMinutes - ($committed[$key] ?? 0)));
                    $committed[$key] = ($committed[$key] ?? 0) + $take;
                    $remaining -= $take;
                }

                $cursor = $cursor->addDay();
            }
        }

        return $committed;
    }

    /**
     * Scheduled requests assigned to this person, as {start, due, minutes}. Once PRD-06
     * turns a request into tasks those tasks are the truth and this reservation has to go,
     * or the same effort is counted twice.
     *
     * @return array<int, array{start: string, due: string, minutes: int}>
     */
    private function reservations(Workspace $workspace, User $user, FeatureRequest|ProjectRequest|null $ignoring = null): array
    {
        $shape = fn ($request) => [
            'start' => (string) $request->scheduled_start,
            'due' => (string) $request->scheduled_due,
            'minutes' => (int) $request->estimated_minutes,
        ];

        $query = fn (string $model, array $statuses) => $model::query()
            ->where('workspace_id', $workspace->id)
            ->where('assignee_id', $user->id)
            ->when($ignoring instanceof $model, fn ($request) => $request->whereKeyNot($ignoring->getKey()))
            ->whereIn('status', $statuses)
            ->whereNotNull('scheduled_start')
            ->whereNotNull('scheduled_due')
            ->whereNotNull('estimated_minutes')
            // Once a breakdown is accepted the tasks it produced are the commitment. Keeping
            // the reservation as well would count the same effort twice.
            ->whereDoesntHave('breakdowns', fn ($breakdown) => $breakdown->where('status', BreakdownStatus::ACCEPTED->value))
            ->get(['id', 'scheduled_start', 'scheduled_due', 'estimated_minutes']);

        return $query(FeatureRequest::class, [FeatureRequestStatus::SCHEDULED->value, FeatureRequestStatus::IN_PROGRESS->value])
            ->concat($query(ProjectRequest::class, [ProjectRequestStatus::SCHEDULED->value, ProjectRequestStatus::IN_PROGRESS->value]))
            ->map($shape)
            ->all();
    }

    /**
     * Days this person cannot work at all: workspace holidays and their own exceptions.
     *
     * @return array<string, true>
     */
    private function blockedDates(Workspace $workspace, User $user): array
    {
        $blocked = [];

        WorkspaceHoliday::where('workspace_id', $workspace->id)
            ->where('observed_on', '>=', now()->startOfDay())
            ->pluck('observed_on')
            ->each(function ($date) use (&$blocked): void {
                $blocked[$date->format('Y-m-d')] = true;
            });

        CapacityException::where('workspace_id', $workspace->id)
            ->where('user_id', $user->id)
            ->where('ends_on', '>=', now()->startOfDay())
            ->get(['starts_on', 'ends_on'])
            ->each(function (CapacityException $exception) use (&$blocked): void {
                $cursor = CarbonImmutable::instance($exception->starts_on);
                $end = CarbonImmutable::instance($exception->ends_on);

                while ($cursor->lte($end)) {
                    $blocked[$cursor->format('Y-m-d')] = true;
                    $cursor = $cursor->addDay();
                }
            });

        return $blocked;
    }

    private function timezone(Workspace $workspace): string
    {
        return in_array($workspace->timezone, timezone_identifiers_list(), true) ? $workspace->timezone : 'UTC';
    }
}
