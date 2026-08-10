# PRD-10 — Delivery Intelligence Phase 16A

Status: implemented, 2026-08-05. This document records the user's decision to begin the proposed Delivery Intelligence roadmap after the original Orbitra and request-to-delivery phases were complete.

AI breakdown integration added 2026-08-05: generated checklist items are persisted with each
accepted task, while generated `depends_on` indices are reviewable and become real
Finish-to-Start links on acceptance.

## Outcome

Orbitra can express finish-to-start task dependencies, derive whether work is blocked, anchor a project around dated milestones, and show both concepts on the existing List, Board, Task Detail, and Timeline surfaces.

## Scope

- A task may wait for any number of other active tasks in the same project.
- A dependency is finish-to-start: an unfinished prerequisite makes the dependent task blocked.
- Blocked is derived from current dependency completion and is never stored as another task status.
- Self-dependencies, cross-project dependencies, and circular chains are rejected.
- A project may have dated milestones; a task may belong to one milestone and a milestone may contain many tasks.
- Milestones appear as diamonds and dependencies as connectors on the project timeline.
- Board cards and task lists identify blocked work; the global list and project list can filter it.
- Moving blocked work into an in-progress status remains possible after an explicit browser confirmation.

## Permissions

1. Workspace owners/admins and the project's Leader can manage milestones.
2. Existing task update policy governs dependency changes, so contributing project members can manage them and viewers cannot.
3. Dependency candidates are restricted to the task's project.
4. Milestone and task route binding remains workspace-membership scoped and all public routes use ULIDs.

## Data rules

- `task_dependencies` has one unique edge per dependent/prerequisite pair and cascades when either task is deleted.
- `project_milestones` belongs to one workspace and project and carries a target date plus optional completion timestamp.
- `tasks.milestone_id` is nullable; removing a milestone keeps its tasks and clears their milestone reference.
- Circular validation runs inside the same transaction that locks project tasks, preventing two concurrent dependency additions from bypassing the check.

## Acceptance criteria

- Adding an unfinished prerequisite immediately marks the task blocked; completing it immediately clears the derived state.
- Direct and indirect cycles return a validation error and write no edge.
- Blocked filters return only blocked, unfinished tasks visible to the user.
- Task Detail explains both “Waiting for” and “Blocking” relationships.
- Board renders blocked badges and confirms a move to In Progress.
- Timeline renders milestone diamonds, dependency connectors, and source task links.
- Leader can create, update, complete, and remove milestones; viewer cannot mutate either planning object.
- PHPUnit, Pint, migration, and the production asset build pass at the phase checkpoint.

## Deferred

- Delivered in Phase 16B (PRD-11): dependency types beyond finish-to-start, cross-project
  dependencies (same workspace), baseline comparison, automatic date shifting, critical-path
  calculation, time tracking, forecast health, portfolio reporting, and AI weekly insights.
