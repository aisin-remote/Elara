# PRD-03 — Feature request intake and approval

Status: **implemented 2026-07-31 (Phase 11)** and Phase 12b (attachments). Depends on: PRD-01, PRD-02.
Blocks: PRD-05.

Shipped: the table, the state machine in `TransitionFeatureRequest`, the requester submission
and detail views, the approvals queue with approve / reject / needs-info, the policy split that
keeps managers out of feature decisions, the `feature_request` notification event, and
`tests/Feature/Request/FeatureRequestFlowTest.php`.

Organization approval enhancement, 2026-08-09: non-IT requesters below `MGR/COOR` first
enter approval by a same-department MGR/COOR. Non-IT MGR/COOR go directly to ITD review.
ITD users work in the delivery desk rather than filing requester-side requests. The external
organization is read from PostgreSQL and snapshotted on submission.

Deferred: **attachments**. `project_files` is keyed to `project_id`, so hanging request
attachments off it would file a requester's draft screenshots into the target system's library.
PRD-04 wants attachments too, so the polymorphic owner column gets added once, in that phase,
for both request types.

## Problem

A non-IT user wants a change to a system that already exists. Today that arrives by chat,
email, or a tap on the shoulder, and the IT team turns it into tasks by hand — losing the
audit trail of who asked, who approved, and when.

## Flow

```
non-IT staff submits ──▶ department MGR/COOR ──▶ pending_review ──▶ ITD SPV
non-IT MGR/COOR submits ───────────────────────▶ pending_review ──▶ ITD SPV
                                                                  ├─ approved ──▶ scheduling
                                                                  └─ rejected
```

One ITD approver, preceded by a department approver only for staff-level requesters. A feature
request is a change to something that already has a budget, an owner, and a maintenance rhythm
— it does not need the two-signature ITD treatment a new project gets.

## Scope

### Data

```
feature_requests
  id, public_id (ULID)
  workspace_id       FK
  project_id         FK -> projects  (the target system, type = 'system')
  requester_id       FK -> users
  organization_user_id, requester_job_rank_code/name
  requester_division_external_id/code/name
  requester_department_external_id/code/name
  requester_section_external_id/name
  department_reviewed_by/at, department_decision_note
  title              string(200)
  problem            text     -- what is wrong or missing today
  desired_outcome    text     -- what "done" looks like from the requester's side
  urgency            string   -- 'low' | 'normal' | 'high'  (a request, not a promise)
  status             string   -- see states below
  reviewed_by        nullable FK -> users
  reviewed_at        nullable datetime
  decision_note      nullable text   -- required when rejected
  feature_id         nullable FK -> features   (set when accepted, PRD-06)
  version            unsigned int
  timestamps, softDeletes
  index (workspace_id, status), index (requester_id)
```

Attachments reuse the existing private-file mechanism: a request can carry screenshots or
documents through the same storage path and policy-guarded download route the project file
library uses.

### States

| State | Meaning | Next |
|---|---|---|
| `draft` | saved, not submitted | `pending_department`, `pending_review` |
| `pending_department` | waiting on same-department MGR/COOR | `pending_review`, `rejected`, `needs_info` |
| `pending_review` | waiting on SPV | `approved`, `rejected`, `needs_info` |
| `needs_info` | department or ITD approver asked a question | return to the requesting stage |
| `approved` | SPV signed off; scheduling begins | `scheduled` |
| `scheduled` | slot and PIC assigned (PRD-05) | `in_progress` |
| `in_progress` | tasks exist and are moving | `delivered`, `taken_down` |
| `delivered` | all tasks complete, validations passed | terminal |
| `rejected` | SPV declined, `decision_note` required | terminal |
| `taken_down` | validation expired (PRD-07) | terminal |

`needs_info` matters more than it looks: without it, an SPV facing a vague request must
either reject it (and lose the thread) or approve it (and push the ambiguity onto the PIC).

### The form

Fields: target system (only systems the workspace has, with a visible PIC name), title,
problem, desired outcome, urgency, attachments. All of problem and desired outcome are
required — they are the input the AI breakdown consumes in PRD-06, and a thin request
produces thin tasks.

The form shows the system's current queue depth ("3 requests ahead of yours") so expectations
are set before submission rather than after.

### The approvals queue

New IT-desk page at `/app/workspaces/{workspace}/approvals`, visible to `supervisor`,
`manager`, `admin`, `owner`. Lists both streams. For each feature request: requester, system,
title, urgency, age, and the full problem/outcome text inline — an approver should not need
to click through to decide.

Actions: Approve, Reject (note required), Ask for info (note required).

The requester desk also has `/desk/workspaces/{workspace}/approvals`, visible only to
non-IT MGR/COOR. It lists pending requests from the same department. A request cannot be
submitted to this gate unless at least one matching approver has active workspace access.

### Notifications

Reusing the existing notification centre and its channel preferences:

| Event | Recipient |
|---|---|
| Staff request submitted | active same-department MGR/COOR |
| Department approved | every ITD `supervisor` in the workspace |
| MGR/COOR request submitted | every ITD `supervisor` in the workspace |
| Approved / rejected / needs info | the requester |
| Scheduled with a date | the requester |

## Permissions

- Create: non-IT `requester` (own only). ITD users create delivery work directly.
- Read: the requester, requesters in the same snapshotted department, and every
  delivery-team member in the workspace.
- Department decision: same-department non-IT `MGR/COOR` only.
- Approve/reject: `supervisor`, `admin`, `owner`. **Not** `manager` — managers are for
  project requests; letting them approve features blurs the two streams.
- Withdraw: the requester, while `draft`, `pending_department`, `pending_review`, or
  `needs_info`.

## Acceptance criteria

- A staff-level submitted request appears in its department queue within one page load; after
  department approval it appears in the ITD queue and notifies supervisors.
- Rejecting without a note is refused by the Form Request, not just the UI.
- An approved request that cannot be scheduled (no PIC capacity found) stays `approved` and
  is surfaced as such rather than silently stalling — PRD-05 defines the fallback.
- A requester sees feature requests from the same department, but policy blocks every other
  department.
- Withdrawal after approval is impossible; the request must be taken down through PRD-07's
  path so the capacity release is recorded.
- Every state transition writes an `ActivityLog` row with the actor and the reason.

## Out of scope

- Effort estimation by the requester. They describe outcomes; PRD-06 estimates effort.
- Cost or budget approval.
- Multi-system requests. One request targets one system; a change spanning two systems is
  two requests, deliberately.

## Open questions

1. Should `urgency = 'high'` jump the scheduling queue, or only sort the approvals queue?
   PRD-05 currently assumes approval order wins and urgency is advisory. Confirm.
2. Is there a size above which a "feature" should become a project request instead? A rule
   like "AI-estimated effort > 10 working days routes to PRD-04" is implementable once PRD-06
   lands, but needs an owner's decision.
