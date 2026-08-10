# PRD-05 — Capacity-aware scheduling and assignment

Status: **implemented 2026-07-31 (Phase 13)**. Depends on: PRD-02, PRD-03, PRD-04, PRD-08
(capacity, holidays, and the tuning constants are entered there). Blocks: PRD-06, PRD-07.

Shipped: `App\Services\CapacityPlanner` (`availableFrom`, `assign`), `App\Services\WorkspaceSettings`,
`App\Actions\Request\ScheduleApprovedRequests` with `orbitra:drain-request-queue` hourly, the
`member_capacities` / `capacity_exceptions` / `workspace_holidays` tables, the effort estimate
collected with each approval, and the Phase 9b master screens for capacity, holidays, and rules.
Covered by `tests/Feature/Schedule/CapacityPlannerTest.php` and `RequestQueueTest.php`.

One reservation to unwind in PRD-06: a scheduled request holds its days through
`CapacityPlanner::reservations()` because it has no tasks yet. The moment the AI breakdown
creates real tasks, that reservation must be dropped or the same effort is counted twice.

## Problem

"The system will determine the nearest free slot, preferring the system's PIC." Orbitra
cannot answer either half of that today. It knows when meetings are (`schedule_events`) and
what tasks exist, but it has no concept of how many hours a person has, how much of that is
already committed, or when the next opening is.

## Decision: commitments, not calendars

Availability is computed from **committed effort per person per working day**, not from
calendar free/busy. A person with a full day of estimated task work is unavailable even with
an empty calendar; a person with two meetings still has capacity for four hours of work.

Meetings are subtracted as overhead, not treated as the source of truth.

## Scope

### Data

```
member_capacities
  id
  workspace_id     FK
  user_id          FK
  hours_per_day    decimal(4,1)  default 6.0   -- deliberately below 8: meetings, support, slack
  working_days     json          default [1,2,3,4,5]   -- ISO weekday numbers
  effective_from   date
  timestamps
  unique (workspace_id, user_id, effective_from)

capacity_exceptions
  id
  workspace_id, user_id  FK
  starts_on, ends_on     date
  reason                 string   -- 'leave' | 'training' | 'other'
  timestamps
```

`hours_per_day` defaults to 6, not 8. A team scheduled at 100% of nominal hours is a team
that misses every date; the buffer is the difference between a plan and a wish.

Both tables, plus the workspace holiday calendar the walk-forward skips, are maintained in
Settings → Master data (PRD-08). So are `pic_grace_days` and `horizon_days` below: they start
as config defaults but an admin can tune them without a deploy.

No new table records "what is committed" — that is derived from `tasks.estimate_minutes`
where the task is assigned, unfinished, and dated. The estimate is already there.

### The slot finder

`App\Services\CapacityPlanner` answers two questions.

**`availableFrom(User $user, int $minutes): CarbonImmutable`** — the earliest working day on
which this person can start a block of work of this size without exceeding their daily
capacity, walking forward day by day, subtracting existing committed estimates and meeting
hours, and skipping non-working days and exceptions.

**`assign(Project $system, int $minutes): array{user: User, starts_at, due_at}`** — the
assignment decision:

1. Try the system's PIC (the first `project_members` row with `role = manager`, PRD-02).
2. Then the system's other managers, by id.
3. Then the system's other members, by id.
4. Then any workspace `member` with capacity, ordered by earliest availability.

The PIC preference is a *preference*, not a lock. If the PIC's first opening is more than
`config('orbitra.assignment.pic_grace_days')` (default 10) later than the next best person's,
the work goes to the next best person and the PIC is recorded as reviewer instead. A queue
behind one expert is how a delivery team acquires a bottleneck.

If no one has capacity inside `config('orbitra.assignment.horizon_days')` (default 90), the
request stays `approved` and enters the **backlog queue** rather than being force-fitted.

### The backlog queue

Approved-but-unscheduled requests are ordered by approval time (FIFO). Two events drain it:

- capacity freeing up (a task completes early, an exception is removed);
- a takedown releasing a whole block of committed effort (PRD-07).

A scheduled command (`orbitra:drain-request-queue`, hourly, `withoutOverlapping()` like the
existing reminder command) re-runs the slot finder for the head of the queue.

This is the mechanism behind "another project replaces the one taken down" — it is not a
special case of takedown, it is the ordinary behaviour of a queue that just gained capacity.

### What the requester sees

On scheduling, the requester's timeline shows the assigned person and the planned window. On
queueing, it shows position and the reason ("no capacity before 28 Oct; you are 2nd in
queue") — a queue that explains itself generates far fewer follow-up messages than one that
does not.

## Acceptance criteria

- A person with `hours_per_day = 6` and 6 hours of committed estimates on Thursday is not
  offered Thursday.
- Non-working days and capacity exceptions are skipped, not merely deprioritised.
- The PIC receives the work whenever their opening is within the grace window; beyond it the
  next candidate does, and the decision plus its reason is written to `ActivityLog`.
- With no candidate inside the horizon, the request enters the queue with a visible position
  and no assignment is invented.
- The planner is deterministic: same data in, same assignment out, with no reliance on
  wall-clock ordering within a day.
- Scheduling is idempotent — running the drain command twice does not double-book.

## Verification

This is the component most likely to be subtly wrong and least likely to be caught by eye, so
it carries the heaviest test burden of the set:

- Unit tests over `CapacityPlanner` with a frozen clock: full day, partially full day,
  weekend, exception window, meeting overhead, horizon exhaustion.
- A test asserting the PIC grace rule tips at exactly the configured boundary.
- A test asserting queue drain assigns the head of the queue, not an arbitrary member.

## Out of scope

- Part-day granularity (start at 13:00). Days are the unit.
- Skill matching beyond system membership.
- Cross-workspace resourcing.
- Rebalancing already-scheduled work when someone goes on leave. The exception blocks *new*
  scheduling; existing assignments are flagged for a human, not silently moved.

## Open questions

1. Does `urgency = 'high'` (PRD-03) preempt the FIFO queue? Preemption needs a rule for what
   it displaces; currently urgency only sorts the approvals queue.
2. Should capacity be per workspace or per person globally? Per workspace today, consistent
   with every other entity.
3. Who maintains `member_capacities` — each member for themselves, or an admin? Assume admin,
   with a member-visible read-only view.
