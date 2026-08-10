# PRD-11 — Delivery Intelligence Phase 16B

Status: implemented, 2026-08-06. Extends PRD-10 (16A) with the deferred planning intelligence items.

## Outcome

Orbitra can compute a project's critical path and slack, push dependent dates when the graph
or estimates change, score forecast health, roll that up into a portfolio view, express
richer dependency types (including cross-project links in the same workspace), log actual
time against tasks, and produce a weekly AI (or rule-based) delivery digest.

## Scope

### Critical path and baseline
- Critical path and total slack are derived from unfinished tasks, estimates, and the
  dependency graph (working-day duration via each assignee's capacity, falling back to the
  workspace default).
- Slack is never stored; critical is a derived flag.
- Capturing a baseline copies current `start_at` / `due_at` into `baseline_*` columns so
  timeline and project health can compare plan vs actual.
- Accepting an AI task breakdown also stamps a baseline for the created tasks.

### Automatic date shifting
- When a dependency is added or a task's estimate/dates change, unfinished dependents are
  pushed later so dependency constraints still hold. Dates are never pulled earlier by the
  automatic pass.
- Shifting uses `CapacityPlanner` so weekends, holidays, and leave stay consistent with
  request scheduling.
- Project Leaders can run an explicit "Reschedule from dependencies" action that applies the
  same pass to the whole project.

### Forecast health
- Each visible project gets a forecast state: `complete`, `on_track`, `at_risk`, or `late`.
- The score combines remaining critical-path effort against the project due date, overdue
  tasks, and the existing schedule-progress gap.
- Surfaces: project show, portfolio, Ask AI tools.

### Portfolio
- A Portfolio page under the delivery desk lists visible projects/systems with progress,
  forecast, blocked count, critical-task count, and projected finish.
- Performance keeps its analytics role; Portfolio is the planning rollup.

### Richer dependencies and time tracking
- Dependency types: finish-to-start (default), start-to-start, finish-to-finish,
  start-to-finish, plus optional lag in minutes.
- Cross-project links are allowed inside the same workspace; other workspaces stay forbidden.
- Blocked (for board/list) remains start-blocking only: FS and SS. FF/SF constrain shifting
  and display but do not mark a card blocked.
- Task time entries record actual minutes worked; totals appear on the task detail.

### AI weekly insights
- `orbitra:generate-weekly-insights` runs weekly, stores a `delivery_insights` row per
  workspace, and notifies owners/admins/managers.
- With an OpenAI key the summary is model-written from portfolio aggregates; without a key a
  deterministic rule-based summary is stored so the feature still works in tests and local
  environments.

## Permissions

1. Dependency and time-entry mutations follow `TaskPolicy::update`.
2. Baseline capture and project-wide reschedule follow `ProjectPolicy::update`.
3. Portfolio follows the existing `viewReport` gate.
4. Weekly insight rows are readable by anyone who can view the workspace report.

## Acceptance criteria

- Critical tasks render distinctly on the project timeline; slack is available to services.
- Adding an FS prerequisite that finishes after the dependent's start pushes the dependent.
- Forecast states match overdue / behind / on-track cases covered by tests.
- Portfolio lists only projects the viewer can see.
- Cross-project same-workspace dependencies are accepted; foreign-workspace ones are not.
- Time entries require positive minutes and appear in the task total.
- The weekly command is idempotent for the same ISO week (one insight row per workspace/week).
- PHPUnit, Pint, and `npm run build` pass at the phase checkpoint.

## Out of scope

- Department / jabatan integration (handled later against an external user directory).
- Ask AI Phase B/C mutations.
- Dependency types beyond the four classic ones, or negative lag.
