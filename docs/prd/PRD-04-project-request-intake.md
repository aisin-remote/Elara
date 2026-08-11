# PRD-04 — Project request intake and approval

Status: **implemented 2026-07-31 (Phase 12a)** and Phase 12b (attachments). Depends on: PRD-01.
Blocks: PRD-05.

Shipped: the table, the eleven-state machine in `TransitionProjectRequest`, the meeting gate
backed by `schedule_events`, sequential signatures with the distinct-approver rule, project
creation on approval, both desk and approvals views, and
`tests/Feature/Request/ProjectRequestFlowTest.php`.

Organization approval enhancement, 2026-08-09: non-IT requesters below `MGR/COOR` first
need a same-department MGR/COOR decision. Non-IT MGR/COOR bypass that gate. The existing ITD
chain remains scoping meeting → SPV → MGR.

Business-case form enhancement, 2026-08-11: the requester-facing Project form now captures
Background, Why Needed, up to six Objectives, Project Illustration, Before/After, up to four
Benefits, up to three Cost Items, and ROI. Feature Request intake remains unchanged. Requester
pages use English throughout.

Deferred to 12b: **attachments**, shared with PRD-03. `files.project_id` is already nullable so
the column work is small; the upload flow, the download policy for a requester who is not a
project member, and the desk UI are the actual cost, and they are the same work for both
request types.

## Problem

A new project is a different commitment from a feature change: no existing owner, no existing
budget, no existing maintenance rhythm. It needs a business case before anyone estimates it,
a conversation before anyone approves it, and two signatures before it consumes a quarter of
someone's year.

## Flow

```
non-IT staff submits ──▶ department MGR/COOR ──▶ pending_meeting
non-IT MGR/COOR submits ───────────────────────▶ pending_meeting
                                                    │
                                                    ▼
                                               meeting held ──▶ pending_spv
                                                                    ├─ rejects ──▶ rejected
                                                                    └─ approves ──▶ pending_manager
                                                                                         ├─ rejects ──▶ rejected
                                                                                         └─ approves ──▶ scheduling
```

The meeting is a gate, not a formality: the request cannot reach an approver until it has
happened. This is the single biggest difference from PRD-03.

## Scope

### Data

```
project_requests
  id, public_id (ULID)
  workspace_id        FK
  requester_id        FK -> users
  organization_user_id, requester_job_rank_code/name
  requester_division_external_id/code/name
  requester_department_external_id/code/name
  requester_section_external_id/name
  department_reviewed_by/at, department_decision_note
  title               string(200)
  background          nullable text
  why_needed          nullable text
  objectives          nullable json  -- [{title, description}], maximum six
  illustration        nullable text
  before_state        nullable text
  after_state         nullable text
  benefits            nullable json  -- maximum four
  cost_items          nullable json  -- maximum three
  roi                  nullable text
  benefit             text   -- required: what the business gains
  concept             text   -- required: what it is
  business_process    text   -- required: the process it supports or replaces
  flow                text   -- required: how it runs end to end
  target_date         nullable date  -- requester's hope, not a commitment
  status              string
  schedule_event_id   nullable FK -> schedule_events  (the scoping meeting)
  meeting_held_at     nullable datetime
  meeting_note        nullable text
  spv_id, spv_at, spv_note                nullable
  manager_id, manager_at, manager_note    nullable
  project_id          nullable FK -> projects   (set on approval)
  version             unsigned int
  timestamps, softDeletes
  index (workspace_id, status), index (requester_id)
```

The structured business-case fields are what requesters edit and approvers read. The four
legacy narrative columns remain populated as generated summaries so the existing approval,
AI breakdown, and project-creation pipeline stays backward compatible with older requests.

### States

| State | Meaning | Next |
|---|---|---|
| `draft` | saved, not submitted | `pending_department`, `pending_meeting` |
| `pending_department` | waiting on same-department MGR/COOR | `pending_meeting`, `rejected`, `needs_info` |
| `pending_meeting` | submitted; scoping meeting not yet held | `pending_spv`, `withdrawn` |
| `pending_spv` | meeting held; first signature outstanding | `pending_manager`, `rejected`, `needs_info` |
| `pending_manager` | SPV approved; second signature outstanding | `approved`, `rejected` |
| `needs_info` | department or ITD approver asked a question | return to the requesting stage |
| `approved` | both signatures in; scheduling begins | `scheduled` |
| `scheduled` → `in_progress` → `delivered` | as PRD-03 | |
| `rejected` | either approver declined, note required | terminal |
| `taken_down` | validation expired (PRD-07) | terminal |

Approval is sequential. A manager approving before the SPV has seen it defeats the purpose of
a first-line filter, so the manager action is unavailable until `pending_spv` clears.

### The scoping meeting

On submission, the request enters `pending_meeting` and every `supervisor` is notified. An
SPV schedules the meeting from the request page, which creates a normal `schedule_events` row
with the requester and the SPV as attendees — reusing the existing scheduling UI, timezone
handling, attendees, and meeting-link support rather than inventing a parallel calendar.

After the meeting the SPV marks it held and records `meeting_note`. Only then does the
approve/reject control appear. The meeting note is shown to the manager, who was probably not
in the room.

If the meeting is never scheduled, the request sits in `pending_meeting` and appears in the
approvals queue with its age — visible, not silently rotting.

### The form

The Project form mirrors the business-case worksheet: Project Name; Background; Why Needed;
one to six titled Objectives; Project Illustration; Before and After; one to four tangible or
intangible Benefits; one to three expected Cost Items; and ROI. The target date remains an
optional planning preference. Each narrative section is validated server-side, and the same
complete form is used when a proposal is returned for more information.

Attachments reuse the private-file mechanism (process diagrams, spreadsheets, mockups).

### On approval

A `projects` row is created with `type = 'project'`, the requester recorded as stakeholder,
the assigned PIC as project manager, and dates from PRD-05. The project then behaves exactly
like any project in Orbitra today — which is the point.

## Permissions

- Create: non-IT `requester` (own only). ITD users create delivery work directly.
- Department decision: same-department non-IT `MGR/COOR` only. The requester desk exposes a
  dedicated Approvals menu to them.
- Schedule meeting / mark held: `supervisor`, `admin`, `owner`.
- First signature: `supervisor`, `admin`, `owner`.
- Second signature: `manager`, `admin`, `owner`. A user holding both roles cannot supply both
  signatures — the second approver must differ from the first, enforced server-side.
- Withdraw: requester, until `pending_manager`.

## Acceptance criteria

- Submitting without Background, Why Needed, at least one complete Objective, Project
  Illustration, Before, After, at least one Benefit, at least one Cost Item, or ROI is refused
  by the Form Request.
- ITD and department approvers see the same structured business case submitted by the
  requester.
- The approve control is unavailable — not merely hidden — until `meeting_held_at` is set.
- The same user cannot occupy both signature slots, even with both roles.
- Approval creates exactly one project, and re-submitting the approval (double click, retry)
  does not create a second: the transition is guarded by the `version` column the way project
  updates already are.
- The created project's members include the PIC as manager; the requester is **not** added as
  a project member (they are a `requester`; they see progress through their own desk).
- Rejection at either stage notifies the requester with the note.
- Project business cases are not shared department-wide; only the requester, authorized ITD
  team, and the department approver who acted retain access.

## Out of scope

- Formal budget authorization. The form records requester estimates and ROI context only.
- Vendor or procurement flow.
- Multi-round approvals beyond the two signatures.
- Portfolio-level prioritisation across approved-but-unscheduled projects beyond the FIFO
  queue in PRD-05.

## Open questions

1. Should a rejected request be re-submittable as a new revision linked to the original, or
   does the requester start clean? Linking preserves history but adds a `supersedes_id`
   column; deferred until the approval owners weigh in.
2. Who schedules the meeting when several supervisors exist — first to claim it, or an
   explicit assignment? Currently first to claim.
