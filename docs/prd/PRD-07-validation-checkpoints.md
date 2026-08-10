# PRD-07 — Validation checkpoints, expiry, and takedown

Status: **implemented 2026-08-03 (Phases 15a, 15b)**. Depends on: PRD-06, PRD-08 (the window
length is set there). Blocks: nothing.

Shipped in 15b — the clock path: `orbitra:sweep-validations` (hourly, `withoutOverlapping()`)
with the midpoint reminder, the 24-hour final warning copied to the PIC and supervisor, and
expiry through `App\Actions\Validation\TakeDownRequest`. Covered by
`tests/Feature/Validation/SweepValidationsTest.php` with a frozen clock.

One rule this document did not state: once the final warning has gone out, the midpoint
reminder no longer fires. A nudge arriving after the last warning reads as a step backwards,
and the sweeper reached both conditions in the same run.

Shipped in 15a — the human path: `validation_checkpoints`, `tasks.requires_user_validation`,
`App\Actions\Validation\OpenValidationCheckpoints` hooked into both completion paths,
`RespondToCheckpoint`, the requester's Validations page, and `ValidationCheckpointPolicy`.
Covered by `tests/Feature/Validation/CheckpointFlowTest.php`.

Deferred to 15b — the clock path: `orbitra:sweep-validations` with the midpoint reminder, the
24-hour final warning to requester plus PIC and SPV, and expiry with takedown, archive, and
capacity release. The frozen-clock tests this document asks for belong there.

One thing the schema could not express: "one open checkpoint per task". MySQL has no partial
unique index, and a unique on `(task_id, status)` would also forbid a second
`changes_requested` — an ordinary second round of review. Enforced in the Action instead.

## Problem

Some tasks produce something only the requester can judge: a screen, a report format, a
migrated dataset. Work should pause there until they confirm it. But a request that waits
forever holds capacity that another request could be using — so the wait needs a deadline,
and the deadline needs teeth.

## The rule

When a task marked `requires_user_validation` (PRD-06) reaches completion, a checkpoint opens
and the requester is asked to review.

```
opened ──(requester approves)──▶ approved ──▶ work continues
   │
   ├──(requester rejects, with note)──▶ changes_requested ──▶ back to the PIC
   │
   ├── day 6: final warning notification
   │
   └── day 7, no response ──▶ expired ──▶ request taken down, capacity released
```

Seven days from the moment the checkpoint opens. One reminder at the midpoint, one final
warning one day before expiry, and the countdown is visible on the requester's desk the whole
time.

## Takedown is not a bug, it is the mechanism

When a checkpoint expires:

1. The feature or project moves to `taken_down` and is archived — using the existing
   `archived_at` machinery, so it is recoverable, not destroyed.
2. Its unfinished tasks are archived with it.
3. The committed effort those tasks represented disappears from the assignee's load, which
   frees capacity.
4. `orbitra:drain-request-queue` (PRD-05) notices the freed capacity and schedules the next
   request in the backlog queue.

Step 4 is the "another project replaces the one taken down" requirement. It needs no special
code path: takedown releases capacity, and the queue drains into released capacity. Building
it as a direct hand-off from the cancelled item to a chosen successor would produce two
mechanisms that must agree — and eventually will not.

## Scope

### Data

```
validation_checkpoints
  id, public_id
  workspace_id      FK
  task_id           FK -> tasks
  subject_type, subject_id   morph -> feature_requests | project_requests
  requester_id      FK -> users
  reason            text        -- why this needs the requester's eyes (from PRD-06)
  status            string      -- 'open' | 'approved' | 'changes_requested' | 'expired' | 'cancelled'
  opened_at         datetime
  expires_at        datetime    -- opened_at + config('orbitra.validation.window_days')
  responded_at      nullable datetime
  response_note     nullable text
  reminded_at, final_warning_at   nullable datetime
  timestamps
  index (workspace_id, status, expires_at)
```

`expires_at` is stored rather than computed so that changing the window later does not
retroactively expire checkpoints that were opened under the old rule. The window length is a
master-data setting (PRD-08), not a code constant — an admin who shortens it to five days
affects the next checkpoint, never the ones already counting down.

### The sweeper

`orbitra:sweep-validations`, hourly, `withoutOverlapping()` — the same shape as the existing
`orbitra:send-deadline-reminders` command, which is the precedent to copy rather than invent
around.

Each run, in one transaction per checkpoint:

- open checkpoints past the midpoint with no `reminded_at` → reminder, stamp `reminded_at`;
- open checkpoints within 24h of `expires_at` with no `final_warning_at` → **final warning**,
  stamp it;
- open checkpoints past `expires_at` → expire, take down the subject, release capacity, write
  an activity record.

Hourly, not per-minute: a deadline measured in days does not need minute precision, and an
hourly sweep is far easier to reason about when something goes wrong at 2am.

### The requester's view

The **Validations** page (PRD-01) lists open checkpoints with what is being asked, why, the
task's deliverable, a countdown in plain language ("4 days left"), and two actions: Approve,
or Request changes with a note. Overdue-soon items are visually distinct, and the consequence
is stated on the page — "if no response by 6 Aug, this request will be taken down" — because
a deadline nobody knows about is not a deadline.

### Notifications

| Moment | Recipient | Channel |
|---|---|---|
| Checkpoint opens | requester | all enabled channels |
| Midpoint | requester | all enabled channels |
| 24h before expiry (final warning) | requester, **and** the PIC and SPV | all enabled channels |
| Expired / taken down | requester, PIC, SPV | all enabled channels |
| Changes requested | PIC | all enabled channels |

The final warning copies the PIC and SPV deliberately: a human who can pick up a phone is a
better failsafe than an automated escalation policy, and by then the work is one day from
being cancelled.

## Acceptance criteria

- A checkpoint expires no earlier than `expires_at`, regardless of when the sweeper runs.
- Expiry archives the subject and its unfinished tasks, and the freed capacity is visible to
  `CapacityPlanner` on the next drain.
- The drain schedules the next queued request after a takedown, in queue order.
- Approving a checkpoint unblocks dependent tasks and does not disturb the schedule.
- Requesting changes returns the task to the PIC and **stops** the countdown — the requester
  responded; the ball is not theirs.
- A cancelled or archived request cancels its open checkpoints rather than leaving orphans.
- The sweeper is idempotent — two runs in the same hour produce one reminder, one warning,
  one expiry.
- Every automated takedown writes an activity record naming the checkpoint that caused it.

## Verification

- A feature test with a frozen clock: open a checkpoint, travel 6 days, assert reminder and
  final warning; travel past expiry, assert takedown, archive, and capacity release.
- A test asserting a takedown drains the queue and schedules the next request.
- A test asserting `changes_requested` stops the countdown.

## Out of scope

- Configurable per-request windows. Seven days is workspace-wide config, not per request.
- Auto-approval on silence. The requirement is explicit: silence takes the work down.
- Reinstating a taken-down request. It is archived and recoverable by an admin, but there is
  no self-service resurrection — a new request is the intended path.
- Escalation to the requester's manager.

## Open questions

1. Does a taken-down request keep its queue position if resubmitted, or start at the back?
   Currently the back, which is the honest default.
2. Should the window pause over weekends and public holidays? Seven calendar days over a long
   weekend is closer to four working days. A working-day window is implementable — the
   capacity model in PRD-05 already knows which days are working days — and probably fairer.
   Needs an owner's decision.
3. Should a partially-delivered request be taken down whole, or only the unvalidated portion?
   Currently whole, which is simpler to explain but blunter.
