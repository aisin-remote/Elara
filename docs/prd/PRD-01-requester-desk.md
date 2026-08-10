# PRD-01 — Requester desk and role model

Status: **implemented 2026-07-31 (Phase 8)**. Depends on: nothing. Blocks: everything else.

Shipped: role enum with capability helpers, full policy audit, `/desk` group and layout,
deny-gate on `/app`, role-aware login redirects, assignable-role source for forms and
validation, seven-role demo data, and `tests/Feature/Workspace/RequesterAccessTest.php`.
Not shipped (belongs to later phases): New request and Validations navigation, which need
PRD-03/04 and PRD-07 to have anything to point at.

Requester monitoring enhancement, 2026-08-05: both feature and project detail pages now show
a shared live timeline. It derives the current stage from approvals, schedule, accepted AI
plan, task/checklist progress, dependencies, and validation checkpoints; polls a
policy-protected endpoint every ten seconds; and exposes counts only, never internal task
titles or board details. Covered by `tests/Feature/Workspace/RequesterMonitoringTest.php`.

IT visibility enhancement, 2026-08-09: requester navigation includes an `IT timeline` page
backed by the central ITD delivery workspace. It exposes dated project progress and scheduled
task titles/status/progress grouped by active contributing IT member, with daily through yearly
scales. It deliberately exposes no internal description, comment, file, dependency, deep link,
or mutation control. Delivery users and accounts without an active requester membership are
denied. Covered by `tests/Feature/Workspace/RequesterItTimelineTest.php`.

Organization directory enhancement, 2026-08-09: a secondary PostgreSQL directory now
classifies each user by corporate rank, division, department, and section. The supported
rank groups are `MGR/COOR`, `SPV/SCH`, and `LDR/STF/SN STF`. ITD ranks map to the matching
delivery roles; non-IT ranks map to requester. Non-IT MGR/COOR users remain on `/desk` and
gain a department Approvals menu. Feature requests are visible across the requester's
department. Project requests remain visible only to the requester, department approver who
acted, and authorized ITD users.

Company login enhancement, 2026-08-09: the existing login form now supports just-in-time
provisioning. When an email exists in the PostgreSQL directory, Orbitra verifies the bcrypt
password there and automatically creates or updates the local MySQL user bridge plus the
department workspace membership. There is no first-access page, OTP, or activation email.
The local row remains necessary for foreign keys, sessions, audit history, and Orbitra-only
preferences; it is not a second password store. Corporate password recovery and changes stay
in the company account service. Local owner/admin accounts remain on local authentication.
Removing a company user from PostgreSQL deprovisions the local bridge on the next session or
workspace sync: required records owned by that user and their descendants are deleted, nullable
audit/approval references are cleared, and an active session is signed out. A recovery command
also prunes records left behind by a manual local deletion performed outside Orbitra.

Department workspace enhancement, 2026-08-09: one corporate department now maps to one home
workspace named `<DEPARTMENT CODE>'s Workspace`. A corporate user has one active department
workspace membership and provisioning moves it automatically when the PostgreSQL department
changes. `ORG_WORKSPACE_PUBLIC_ID` remains the central ITD delivery workspace: non-IT users
submit from their own workspace, while the single request record, ITD approvals, schedules,
features, projects, and generated tasks live there. Department approval and requester
notifications are routed back to the home workspace. This keeps membership aligned with the
directory without duplicating delivery records between tenants.

## Problem

Orbitra has one desk and four roles: owner, admin, member, viewer. All four see the same
navigation — Dashboard, Projects, Schedule, Performance, Task List, Messages, Team,
Settings. A `viewer` sees less data but the same shape of application.

The people who request work are not on the IT team. They should never see a Kanban board, a
Gantt chart, or a member workload chart. They need three things: submit a request, see where
their requests stand, and answer when asked to validate something.

Equally, the approval chain needs two authorities the role model cannot express: a
**supervisor** who approves feature requests and runs scoping meetings, and a **manager**
who co-approves project requests.

## Decision: department home workspaces, central ITD delivery workspace

A requester is a member of the workspace for their PostgreSQL department with a role that
grants no access to delivery views. ITD users belong to the configured delivery workspace.
The request object itself is stored once in that delivery workspace and carries an immutable
organization snapshot used for department visibility and approval.

`App\Enums\WorkspaceRole` gains:

| Role | Sees | Approves |
|---|---|---|
| `owner` | everything (unchanged) | everything |
| `admin` | everything (unchanged) | everything |
| `manager` | IT desk + approvals queue | project requests (second signature) |
| `supervisor` | IT desk + approvals queue | feature requests, project requests (first signature) |
| `member` | IT desk (unchanged) | nothing |
| `viewer` | IT desk, read-only (unchanged) | nothing |
| `requester` | **requester desk only** | nothing |

`requester` is the only role that changes the shape of the application rather than the depth
of access. Everything else is additive.

### Why the request remains in ITD

Department workspaces define identity, membership, navigation, department approvals, and the
requester's notification context. They do not own a duplicate delivery pipeline. Keeping the
request, its activity, attachment, ITD approval, schedule, feature/project, and generated task
in one ITD workspace preserves one source of truth. Department access is policy-driven from
the stored department snapshot, so both desks look at the same object without synchronization.

### Why not reuse `viewer`

`viewer` is a read-only IT team member: it can see projects, tasks, and boards. A requester
must not. Overloading `viewer` would mean every policy that currently says "viewer can read"
grows an exception, which is exactly the ripple this decision is trying to avoid.

## Scope

### Data

- `workspace_members.role` accepts the two new values. No schema change (the column is a
  string; the enum is application-side), but the seeder, factories, and any `whereIn` on
  role values need auditing.
- `users` stores `auth_source`, the stable organization user ID, and the last synchronization
  time. A person's authorization role remains on the workspace membership.

### Routes and layout

- New route group `/desk/*` guarded by a `requester` gate, using a new
  `layouts/requester.blade.php` — narrower navigation: My requests, New request, Validations,
  Profile.
- The existing `/app/*` group gains a gate that **denies** `requester`. This is a deny-list,
  not an allow-list, so a new IT-side route is safe by default.
- `requester` landing after login is `/desk`, not `/app`. The post-login redirect currently
  assumes one destination.

### Permissions

Every existing policy is audited for the two new roles. The rule of thumb:

- `manager` and `supervisor` behave as `member` for delivery objects, plus the approval
  abilities introduced in PRD-03 and PRD-04.
- `requester` is denied by every existing policy. New policies govern their own requests.

`WorkspacePolicy`, `ProjectPolicy`, `TaskPolicy`, and `WorkspaceMemberPolicy` are the ones
with role checks today; the rest derive from project membership and inherit the answer.

### Requester desk contents

**My requests** — a list of everything this user submitted, both streams, with a status
timeline (Submitted → Under review → Scheduled → In progress → Awaiting your validation →
Delivered / Rejected / Taken down). No task detail: a requester sees progress percentage and
the current stage, not the task board.

**New request** — a chooser leading to the feature form (PRD-03) or the project form
(PRD-04).

**Validations** — checkpoint items awaiting this user, with the seven-day countdown made
explicit (PRD-07).

**Profile** — the existing profile settings page, minus workspace-level sections.

## Acceptance criteria

- A `requester` who navigates to any `/app/*` URL receives 403, including deep links to a
  task or project they are named on.
- A `requester` does not appear in project member pickers, task assignee pickers, or the
  member workload chart.
- A `requester` receives notifications about their own requests only.
- `supervisor` and `manager` see the IT desk exactly as `member` does, plus an Approvals
  entry in the navigation whose badge counts pending items.
- Changing a member's role to `requester` immediately removes their access to delivery views
  without requiring re-login (policies are evaluated per request; sessions are not cached).
- The demo seeder produces at least one user per new role so the desk can be exercised
  locally.

## Out of scope

- Public self-service signup while company login is enabled. Eligible directory users are
  provisioned automatically at their first successful login.
- Delegation (requester A answering a validation on behalf of B).
- Synchronizing or editing corporate organization data from Orbitra. PostgreSQL is read-only
  from the application's perspective.

## Open questions

1. Can one person be both `supervisor` and a system PIC? Assume yes — the PIC is a property
   of a system (PRD-02), not a role.
2. Corporate directory users with more than one department currently fail closed. Define a
   primary-department field in the source before supporting that case.
