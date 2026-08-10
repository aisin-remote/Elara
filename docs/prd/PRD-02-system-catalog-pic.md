# PRD-02 — System catalog and PIC ownership

Status: **implemented 2026-07-31 (Phase 10)**. Depends on: PRD-01, PRD-08 (its admin screen).
Blocks: PRD-03, PRD-05, PRD-06.

Shipped: `projects.type` with `delivery()`/`systems()` scopes, `features` table,
`tasks.feature_id`, the Feature menu, the Systems master with PIC rules, the full
project-surface audit, and `tests/Feature/Project/SystemCatalogTest.php`.

Two decisions worth carrying forward. Surfaces that list *work* rather than *projects* — the
Task List and Performance filters — show both types under optgroups instead of hiding systems,
because system tasks appear in those lists either way and a filter that cannot name them leaves
rows the user cannot narrow. And the Feature menu shows features as empty until PRD-03 lands:
features are created by approved requests, never by hand.

## Problem

A feature request is always *against something*: "add an export button to the Inventory
system". Orbitra has no record of the Inventory system. It has projects, which are units of
delivery, not standing systems.

Each system also has a person who knows it best. When a request for Inventory is approved,
it should go to that person first, and only spill to someone else if they have no capacity.
There is no place to record that today.

## Decision: a system is a project with `type = 'system'`

`projects` gains:

```
type          string, default 'project'   -- 'project' | 'system'
```

A system-typed project is created once and never "completes". It owns:

- its own `task_statuses` rows (already per-project) — the workflow its maintenance work
  follows;
- its own tasks, board, calendar, timeline, file library, and progress — all existing code;
- its own members, which is where the PIC lives.

### Why this and not a `systems` table

A feature's tasks need statuses, ordering, assignees, checklists, comments, attachments, and
policy checks. Every one of those is implemented today against `tasks.project_id`, which is
`NOT NULL` and constrained. A separate `systems` table means either making `tasks.project_id`
nullable and polymorphic — touching every query, policy, and view in the task, board,
calendar, timeline, and search subsystems — or building a second task pipeline beside the
first.

Modelling a system as a long-lived project costs one nullable column and a scope. It buys
the entire existing delivery stack unchanged.

The visible cost: "Projects" and "Systems" must be filtered apart everywhere a project list
appears (projects index, sidebar quick access, dashboard gantt, search, dashboard project
filters, task list project filter). That is a known, enumerable list — it is a scope applied
in a handful of query builders, not a schema fork.

### PIC

`project_members` already carries a role (`manager` | `member` | `viewer`). A system's PIC is
its member with `role = manager`. Multiple managers are allowed; the first by `id` is the
primary PIC for assignment purposes, and PRD-05 walks the rest in order.

A dedicated `is_pic` flag was considered and rejected: `manager` on a system already means
"the person accountable for this system", and a second overlapping concept invites drift.

## Scope

### Data

```
projects.type                string default 'project'   -- indexed with workspace_id
features                     new table (see below)
tasks.feature_id             nullable FK -> features, nullOnDelete
```

```
features
  id, public_id (ULID)
  workspace_id      FK
  project_id        FK -> projects (the system)
  feature_request_id nullable FK -> feature_requests (PRD-03)
  name, description
  status            string  -- 'scheduled' | 'in_progress' | 'delivered' | 'taken_down'
  starts_at, due_at nullable datetime
  version           unsigned int (optimistic locking, matching projects/tasks)
  archived_at, timestamps, softDeletes
```

A feature is a container for the tasks produced by one approved request. Its tasks carry
both `project_id` (the system, so statuses and boards work) and `feature_id` (so the Feature
menu can group them).

### The Feature menu

New IT-desk navigation entry between Projects and Schedule. Two levels:

**Index** — one card per system the user can see: name, colour, PIC avatar, count of active
features, count of open tasks, and a progress bar derived from the same task data the
project progress already uses.

**Show** — one system: its features listed with their own progress, each expanding to the
tasks inside. Task rows reuse the existing task components; the board, calendar, and
timeline tabs for a system are the existing project views scoped to `type = 'system'`.

### Catalog administration

Systems are created and maintained by users with full Settings access in
**Settings → Master data → Systems** (PRD-08): name, description, colour, PIC, and optional
additional maintainers. A system cannot
be deleted while it has active features; it can be archived, which hides it from the request
form while leaving its history intact.

The master screen is the only place a system is created. Nothing about a system should ever
require editing the database — that is the whole point of PRD-08.

## Acceptance criteria

- The projects index, sidebar quick access, dashboard timeline, and global search show only
  `type = 'project'`. The Feature menu shows only `type = 'system'`.
- A system with no features renders an empty state, not a broken card.
- A task created under a feature appears on the system's board and in the system's task list,
  and counts toward the system's progress.
- Archiving a system removes it from the feature-request form's system picker but leaves its
  history intact.
- A feature request cannot be submitted against a system with no PIC — the form blocks it and
  says why.
- Existing projects are migrated with `type = 'project'`; no existing query changes meaning.

## Out of scope

- Automatic discovery of systems from code repositories or integrations.
- System-level SLAs or uptime tracking.
- Per-system request quotas.

## Open questions

1. Should a system carry a secondary PIC used explicitly for holidays, or is "the next
   manager by id" (PRD-05) sufficient? The latter is simpler and is the current assumption.
2. Do systems need their own document/spec library beyond the existing project file library?
   Assume no for now — the file library is already per-project.
