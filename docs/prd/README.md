# Orbitra — Request-to-Delivery PRD Set

Status: draft, 2026-07-30. Author: engineering. Audience: product + IT leadership.

## Why this set exists

Orbitra today is a single desk for the IT team. Work is created by hand: someone opens a
project, someone types tasks, someone assigns them. The flow we actually want starts one
step earlier — **outside** the IT team — and ends with tasks the IT team never had to write.

Two intake streams feed the same delivery desk:

1. **Feature request** — a non-IT user asks for a change to an existing system. Staff-level
   requesters first need their department MGR/COOR; ITD SPV is the delivery approver. Work
   lands under the system's PIC.
2. **Project request** — a non-IT user asks for something new. Staff-level requesters first
   need their department MGR/COOR, then a scoping meeting and two ITD approvers (SPV, MGR).

Both streams then share the same spine: schedule against real capacity → AI breaks the
request into tasks with estimates → PIC executes → user validates at checkpoints → work that
stalls at validation is taken down and its capacity is handed to the next request in queue.

Organization directory enhancement (implemented 2026-08-09): Orbitra reads corporate users,
job ranks, divisions, departments, and sections from a secondary PostgreSQL connection. The
directory remains authoritative; request rows keep a submission-time snapshot for audit.
Corporate `MGR/COOR`, `SPV/SCH`, and `LDR/STF/SN STF` are the only accepted rank groups.

Unlimited access enhancement (implemented 2026-08-09): the later product decision removes
Starter/Pro/Enterprise quotas and all active billing entry points. Members, projects,
application storage quota, integrations, and CSV/PDF exports are unrestricted by Orbitra.
Infrastructure capacity and provider-side limits still apply outside the application.

## Reading order

| # | Document | What it decides |
|---|---|---|
| 01 | [Requester desk and role model](PRD-01-requester-desk.md) | Who the non-IT user is, what they see, how roles are extended |
| 02 | [System catalog and PIC ownership](PRD-02-system-catalog-pic.md) | What "an existing system" is as data, and who owns it |
| 03 | [Feature request intake and approval](PRD-03-feature-request-intake.md) | Feature: conditional department gate → ITD SPV |
| 04 | [Project request intake and approval](PRD-04-project-request-intake.md) | Project: conditional department gate → meeting → ITD SPV + MGR |
| 05 | [Capacity-aware scheduling and assignment](PRD-05-capacity-scheduling.md) | How the system picks the date and the person |
| 06 | [AI task breakdown and estimation](PRD-06-ai-task-breakdown.md) | How tasks stop being written by hand |
| 07 | [Validation checkpoints, expiry, takedown](PRD-07-validation-checkpoints.md) | The seven-day rule and what replaces cancelled work |
| 08 | [Master data administration](PRD-08-master-data.md) | Where reference data is edited instead of in the database |
| 09 | [Ask AI Phase A](PRD-09-ask-ai-phase-a.md) | Read-only workspace copilot, privacy boundary, and phased AI scope |
| 10 | [Delivery Intelligence Phase 16A](PRD-10-delivery-intelligence-phase-16a.md) | Finish-to-start dependencies, blocked work, milestones, and timeline planning |
| 11 | [Delivery Intelligence Phase 16B](PRD-11-delivery-intelligence-phase-16b.md) | Critical path, auto date shift, forecast, portfolio, richer deps, time tracking, weekly insights |
| 12 | [Supporting Tasks](PRD-12-supporting-tasks.md) | Operational IT work outside projects, systems, and features |

Read 01 and 02 first regardless of what you build. They define the vocabulary every other
document uses, and they carry the two structural decisions that are expensive to reverse.

## Building from these documents

These PRDs say *what* and *why*. The technical rules, phase order, environment traps, and
ready-to-paste session prompts live in
[Prompt_Teknis_Orbitra_Request_To_Delivery.txt](Prompt_Teknis_Orbitra_Request_To_Delivery.txt) —
the same PRD-plus-technical-prompt pairing the original build used. Read it before writing
code; its section A alone documents four ways this environment silently misleads you.

## The two structural decisions

**A requester lives in the same workspace, not a separate tenant.** Every domain object in
Orbitra is already workspace-scoped — projects, tasks, files, notifications, activity, and
search. Splitting non-IT users into their own tenant would mean duplicating all of
it and then building a bridge back. Instead the workspace gains new roles, and the requester
desk is a separate set of routes and a separate layout over the same data. See PRD-01.

**An existing system is modelled as a project.** A feature needs statuses, a board, a
calendar, progress, file attachments, and permission checks — all of which Orbitra already
implements against `projects`. Introducing a parallel `systems` table with its own task
pipeline would fork every one of those. Instead `projects` gains a `type` column
(`project` | `system`), and features hang off a system-typed project. See PRD-02.

## What already exists and is reused as-is

Worth stating plainly, because it is most of the machinery:

- Workspace membership, invitations, activity log, notification centre with mail/broadcast/
  web-push delivery and per-channel preferences.
- Projects with members, roles, archive/restore, optimistic locking, progress derived from
  real task data.
- Tasks with per-project statuses, priorities, assignees, checklists, comments, private file
  attachments, `estimate_minutes`, `due_at`, `completed_at`, archive/restore.
- Kanban board with sparse ordering and idempotent moves; list, calendar, and the new Gantt
  timeline view.
- Schedule events with attendees, timezone handling, and overlap warnings.
- Queues, database notifications, and an hourly scheduled command as a working precedent.

## What does not exist yet

This list described the codebase on 2026-07-30, before any of these PRDs were built. Struck
items are done; what remains is the honest gap.

- ~~Any role between `member` and `admin`~~ — Phase 8. Manager, supervisor, and requester
  exist, and `/app` is closed to requesters.
- ~~Any record of a request that is not yet work~~ — Phases 11 and 12. `feature_requests` and
  `project_requests` both carry their own state machine.
- ~~Any capacity, working-hours, or availability model~~ — Phase 13. `CapacityPlanner` answers
  "who is free on Thursday" from committed effort, not from calendar free/busy.
- ~~Any queue of pending work waiting for capacity to free up~~ — Phase 13.
  `orbitra:drain-request-queue` runs hourly, oldest approval first.
- ~~Any screen for editing reference data~~ — Phases 9a, 10, and 13. Everything under
  Settings → Master data.
- ~~Any LLM integration~~ — Phase 14 and Ask AI Phase A. OpenAI now produces task
  breakdowns and powers a personal, permission-aware, read-only workspace copilot. See PRD-09.
- Any deadline that cancels work. The only scheduled commands send deadline reminders and
  drain the queue; nothing takes work down (PRD-07).

## Delivery order

The dependency chain is real and mostly linear:

```
01 roles ─┬─ 08 master data ─┬─ 02 systems ── 03 feature intake ─┐
          │                  │                                    ├─ 05 scheduling ── 06 AI breakdown ── 07 validation
          │                  └──────────────── 04 project intake ─┘
          └─ (08 also carries capacity, holidays, and request rules for 05 and 07)
```

01 and 08 are foundations: roles decide who may do anything, and master data is where the
systems, capacities, holidays, and rules the later documents consume are actually entered.
02 needs 08's system screen to be useful. 03 and 04 can be built in parallel once 02 lands.
05 must exist before either stream can be "accepted" end to end. 06 depends on 05 because an
estimate without a slot is not schedulable. 07 depends on 06 because a checkpoint is a task
attribute.

A useful first cut that delivers value without the hard parts: 01 + 08 (systems and
categories only) + 02 + 03, with manual assignment and manual task entry. That alone moves
intake off email, gives IT a queue, and stops reference data from living in the database.
