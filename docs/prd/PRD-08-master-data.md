# PRD-08 — Master data administration

Status: **implemented 2026-07-31 (Phases 9a, 10, 13)**. Depends on: PRD-01. Blocks: PRD-02,
PRD-05, PRD-07 (their reference data lives here).

Shipped in 9a — the masters with a consumer today: task categories, task status templates,
help articles, plus the shared page shape, the `manageMasterData` gate, archive-with-
replacement, and `tests/Feature/Workspace/MasterDataTest.php`.

Shipped in 10: **Systems**, once `projects.type` existed for its rows to live in.

Updated 2026-08-21: **Departments** reads the live department catalog from the organization
PostgreSQL connection and stores only a workspace-scoped default PIC. System forms select a
department and show its resolved PIC instead of asking for the same person repeatedly.

Shipped in 13 (the deferred 9b): **member capacity, capacity exceptions, holidays, and request
rules**, built beside the `CapacityPlanner` that reads them rather than ahead of it — a form
for data nothing reads yet is a guess at its shape. The rules screen edits the three tuning
numbers in `WorkspaceSettings::KEYS`; everything else falls back to `config/orbitra.php`.

## Problem

Several kinds of data in Orbitra can only be changed by editing the database or re-running a
seeder. That is fine while one developer runs everything and untenable the moment an admin
needs to add a system, rename a category, or block a public holiday.

What is actually missing today, verified against the code:

| Data | Today | Consequence |
|---|---|---|
| Task categories | one `store` endpoint (`internal.task-categories.store`); no list, edit, or delete | a typo is permanent |
| Knowledge base articles | `SupportArticleSeeder` only; the app has read-only `help` routes | content changes need a deploy |
| Per-project task statuses | created by `CreateProject`, editable per project on the board | every new project repeats the same setup by hand |
| Systems / existing projects | does not exist yet (PRD-02) | — |
| Member capacity, working days, leave | does not exist yet (PRD-05) | — |
| Holidays | does not exist | seven-day windows and capacity walks ignore them |
| Request rules (validation window, PIC grace, horizon) | `config()` constants (PRD-05, PRD-07) | changing a policy needs a code change |

## Decision: one Master data section, one CRUD pattern

A single entry in Settings — **Master data** — holding every reference table, each rendered
by the same pattern: searchable table, inline create, edit, archive, and an audit trail. One
pattern rather than nine bespoke screens, because these pages are all the same shape and the
only thing that differs is the column list.

Restricted to `owner` and `admin` in ordinary workspaces. In the organization-managed ITD
workspace, every contributing IT role (`manager`, `supervisor`, and `member`) has full access
to Workspace settings, Master data, and Integrations. `viewer` remains read-only and
`requester` remains outside the delivery desk.

### Archive, do not delete

Every master row is referenced by something: a task points at a category, a feature points at
a system, a schedule points at a capacity. Hard deletion either orphans those references or
cascades into history. Every master therefore archives (`archived_at`, the mechanism projects
and tasks already use) and archived rows disappear from pickers while remaining resolvable in
historical records.

A row that is still referenced by *active* work cannot be archived without choosing a
replacement — the same "move tasks to…" pattern the board already uses when archiving a task
status.

## Scope

### The masters

**Departments** — read-only identity (id, code, and name) from PostgreSQL plus one editable
default IT PIC stored in Orbitra. Updating a PIC synchronizes existing systems already linked
to that department. Orbitra never copies or owns the corporate department catalog.

**Systems** (PRD-02) — name, description, colour, departments served, derived PICs, and status. This is
the "master existing project" the flow needs: the catalog a feature request picks from.
Creating one here creates a `projects` row with `type = 'system'`.

**Projects** — read-mostly. Delivery projects are created by approved project requests
(PRD-04), not typed in by hand. The master screen exists to correct metadata (name, colour,
dates, manager) and to archive or restore, not to bypass the request flow. Direct creation
stays available to `owner`/`admin` for work that genuinely predates the request system.

**Task categories** — name, colour, per workspace. Completes the half-built feature: list,
edit, archive, and a replacement picker when a category in use is archived.

**Task status templates** — an ordered list of statuses (name, colour, category) applied to
every newly created project and system. Today `CreateProject` seeds a fixed set in code;
this makes the default editable without a deploy. Existing per-project status editing on the
board is unchanged — a template is a starting point, not a constraint.

**Member capacity** (PRD-05) — hours per day, working days, effective-from, per member. Plus
capacity exceptions (leave, training) with a date range and reason.

**Holiday calendar** — workspace-wide non-working dates with a name. Consumed by
`CapacityPlanner` when walking forward for a slot, and by the validation window if PRD-07's
open question resolves toward working days.

**Request rules** — the numbers currently living in `config()`: validation window days, PIC
grace days, scheduling horizon days, and whether AI breakdown is enabled. Moving them into
workspace settings means an admin can tune the policy; keeping their defaults in config means
a fresh workspace still behaves sensibly.

**Knowledge base articles** — title, slug, body, category, published flag. The public help
routes already exist and read from `support_articles`; only the authoring side is missing.

### Deliberately not masters

- **Roles, priorities, task status categories, request statuses** — these are PHP enums that
  code branches on. Making them editable means arbitrary values reaching `match` expressions
  that cannot handle them. They change with a deploy, on purpose.
- **Plans and prices** — `config/plans.php` is server-owned and paired with Stripe price IDs.
  Editable billing tiers are a revenue bug waiting to happen.
- **Workspace members** — already managed on the Team page.

### The shared pattern

One `MasterDataController` per master, all following the shape the existing settings
controllers already use: Form Request validation, a Policy check, an `ActivityLog` write per
mutation, and optimistic locking via `version` on anything a second admin might edit
concurrently.

The table view is one Blade component parameterised by columns and row actions, so adding the
tenth master is a controller plus a column definition, not another screen.

## Acceptance criteria

- Every master supports list with search, create, edit, and archive, without touching the
  database directly.
- Archiving a row that active work still references is refused, with the referencing count
  shown and a replacement picker offered where a replacement makes sense.
- Archived rows vanish from every picker (request forms, task forms, assignment) but still
  render correctly in historical tasks, requests, and activity records.
- Every mutation writes an `ActivityLog` entry naming the actor, the master, and the change.
- A `member`, `supervisor`, `manager`, or `requester` receives 403 on every master route.
- Creating a system from the master screen produces a `projects` row with `type = 'system'`
  and its PIC as a `manager` member, indistinguishable from one created any other way.
- Changing a request rule takes effect on the next evaluation without a deploy or restart, and
  does not retroactively change already-open checkpoints (PRD-07 stores `expires_at`).
- Seeded demo data remains editable through the UI — the seeder is a starting point, not a
  parallel source of truth.

## Out of scope

- Import/export of master data (CSV, Excel).
- Versioned history with rollback per row. The activity log records what changed and who
  changed it; restoring a previous value is manual.
- Cross-workspace master sharing. Every master is workspace-scoped, like everything else.
- Bulk edit.

## Open questions

1. Should archiving a system cascade-archive its features, or block while features are
   active? Blocking is safer and matches the task-status pattern; confirm with operations.
2. Do holidays need to vary by member location? A single workspace calendar is assumed;
   per-location calendars need a location field on membership that does not exist.
3. Should request rules be workspace-scoped or global for the deployment? Workspace-scoped is
   consistent, but a single-tenant install may prefer one global setting.
