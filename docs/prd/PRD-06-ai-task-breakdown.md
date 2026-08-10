# PRD-06 — AI task breakdown and estimation

Status: **implemented 2026-08-03 (Phases 14a, 14b)**. Depends on: PRD-05. Blocks: PRD-07.

Shipped in 14a — the generation path: `task_breakdowns`, `App\Contracts\TaskBreakdownGenerator`
with `App\Services\Ai\OpenAiTaskBreakdown`, `App\Jobs\GenerateTaskBreakdown` dispatched on
approval, the strict JSON schema, and the refusal/error/timeout/no-key branches. Covered by
`tests/Feature/Ai/TaskBreakdownTest.php` using `Http::fake`, never a real call.

Shipped in 14b — everything that turns a draft into work: the review panel on both approval
screens, `App\Actions\Ai\AcceptTaskBreakdown`, `CapacityPlanner::layOut()`, the reservation
release, regenerate-with-a-note, discard, the scheduling-preview endpoint, and the per-workspace
model override. Covered by `tests/Feature/Ai/AcceptBreakdownTest.php`.

Phase 16A follow-up (2026-08-05): every generated task now includes an editable checklist,
and the existing `depends_on` proposal is editable during review and persisted to the real
`task_dependencies` graph. Checklist completion is the source of the task percentage shown
on My Focus and the Gantt timeline.

One deviation from this document, deliberate:

- **A fifth status, `discarded`.** The four sketched below have no room for "a human said no",
  and reusing `failed` would claim the provider broke when it did not. A discarded draft stays
  readable rather than being deleted.
The default model is `gpt-4o`, verified end to end on 2026-08-03. See "Model and cost" below
for what happened to the unverified price table this document used to carry.

## Problem

Every approved request must become tasks with estimates. Doing that by hand is the step this
product exists to remove — and it is also the step that decides the schedule, because PRD-05
cannot place work without knowing how much work there is.

## Decision: the model produces a plan, a human accepts it

The model drafts; the PIC or SPV accepts, edits, or regenerates. Tasks are written to the
database only on acceptance.

This is not hedging. An estimate that lands in someone's calendar without a human ever
looking at it converts a wrong guess directly into a missed commitment, and the person who
pays is the PIC who never saw it. Review costs one click when the draft is good.

## Provider: OpenAI

The workspace owner already holds an OpenAI API key, so OpenAI is the provider. Nothing in
this document depends on the choice beyond one service class — the breakdown is requested
through an interface (`TaskBreakdownGenerator`) with one implementation, so swapping provider
later is a new class and a config value, not a rewrite.

### Client

There is **no official OpenAI PHP SDK**. Two workable paths:

1. **Laravel's HTTP client against the REST API** — recommended. `Http::withToken()->post()`
   against the Responses endpoint is roughly ten lines, adds no dependency, and cannot fall
   behind the API surface we need.
2. `openai-php/laravel` — a community package with typed responses. Convenient, but it is a
   third-party dependency in the path of a core flow, and its coverage of newer endpoints
   trails the API.

Path 1 unless the team wants the typed ergonomics badly enough to own the dependency.

Credentials live in `config/services.php` under an `openai` key, read from the environment,
matching how Stripe and the OAuth providers are already configured. No key is committed;
`.env.example` documents `OPENAI_API_KEY` and `OPENAI_MODEL`.

### Model and cost

The default is **`gpt-4o`**, confirmed working against the Responses API with strict JSON
schema output on 2026-08-03 — it answered as `gpt-4o-2024-08-06` and produced usable
breakdowns of seven and eight tasks.

An earlier draft of this document listed a table of model IDs and per-token prices. Those
numbers could not be verified against OpenAI's catalogue and have been removed rather than
left to be believed. **Check current model IDs and pricing on OpenAI's own pricing page**; a
wrong ID surfaces as an ordinary API failure with the reason visible, but a wrong price
surfaces as a surprise on the invoice.

What is safe to say without a price list: a breakdown is a small request — a few thousand
input tokens of request text plus system context, under a thousand output tokens — so cost per
breakdown is dominated by how often it runs, not by which model runs it. The model is a config
value (`services.openai.model`), overridable per workspace in Master data (PRD-08), so
comparing output quality needs no deploy. Historical rows keep the model that produced them.

### Structured output, not prose parsing

The Responses API constrains output to a JSON Schema, so the result is schema-valid by
construction rather than by regex:

```
text: {
  format: {
    type:   "json_schema",
    name:   "task_breakdown",
    strict: true,
    schema: { ...the shape below... }
  }
}
```

Schema:

```
tasks: array of
  title            string
  description      string
  estimate_minutes integer
  checklist        array of 2–8 concrete completion steps
  depends_on       array of integers (indices of earlier tasks)
  requires_user_validation  boolean     -- feeds PRD-07
  validation_reason         string|null -- shown to the requester when true
```

`requires_user_validation` is the model's judgement about which steps produce something the
requester must confirm — a UI change, a report format, a migrated dataset. PRD-07 turns each
of those into a checkpoint. The human reviewing the draft can add or remove them.

**Refusals are a real branch.** When the model declines, the response carries a `refusal`
field instead of schema-conforming output. Code that reads the structured result without
checking for it will crash on an edge case nobody can reproduce on demand. The refusal path is
treated exactly like an API failure: the request stays `approved`, the reason is recorded, and
manual entry stays available.

### Prompt inputs

- The request itself (problem + desired outcome, or the four narrative fields from PRD-04).
- The target system: name, description, and the titles of its ten most recent completed
  tasks — grounding the estimate in how this team actually breaks down work on this system,
  which is the single highest-value context available.
- The workspace's task statuses, so proposed tasks fit the real workflow.

Keep the system-context block first and byte-stable across requests for the same system, and
the request-specific text last. Provider-side prompt caching, where it applies, keys off a
stable prefix — and even without it, a stable prefix makes two breakdowns for one system
comparable.

### Execution

The call runs in a queued job (`GenerateTaskBreakdown`), not in the request cycle — the
database queue driver is already configured and in use. The request page polls or receives a
broadcast (Echo is already wired) and shows a skeleton while it runs.

Failure is a first-class state: an API error, a refusal, a rate limit, or a timeout leaves the
request in `approved` with a `breakdown_failed` marker and a retry control, plus a
manual-entry escape hatch. The delivery flow must never be blocked by an unavailable third
party.

## Scope

### Data

```
task_breakdowns
  id, public_id
  workspace_id            FK
  subject_type, subject_id   morph -> feature_requests | project_requests
  provider                string        -- 'openai'
  model                   string        -- the exact model id that produced it
  status                  string        -- 'pending' | 'ready' | 'accepted' | 'failed'
  payload_json            json          -- the raw proposed tasks
  input_tokens, output_tokens  unsigned int   -- cost visibility
  error_message           nullable text -- API error or refusal text
  generated_at, accepted_at, accepted_by
  timestamps
```

`provider` alongside `model` because a workspace that switches later still needs its history
to say what produced each plan. Keeping the raw payload separate from the tasks means a
rejected draft is still auditable and a regeneration can be diffed against its predecessor.

### Review screen

Proposed tasks in order: title, description, estimate (editable), checklist items (editable),
dependency selections among earlier tasks, and validation flag. Total effort in hours at the
top, alongside what PRD-05 says that total means for the delivery date — the reviewer is
deciding a date as much as a task list, and should see both.

Actions: accept all, edit inline then accept, regenerate with a note, or discard and enter
manually.

On acceptance: tasks, checklist items, and Finish-to-Start dependency links are created inside
a transaction against the feature (PRD-02) or project (PRD-04), assigned per PRD-05, with
`estimate_minutes` set and checkpoints created per PRD-07.

## Acceptance criteria

- A breakdown never writes tasks without an explicit human acceptance.
- Editing an estimate before acceptance re-runs the scheduling preview.
- AI checklist items and prerequisite selections can be edited before acceptance and become
  the task's real checklist and dependency links.
- A refusal and an API failure both land in `failed` with the reason visible, and neither
  blocks manual entry.
- Token counts and the model id are recorded per breakdown, so cost per request is answerable
  without guessing.
- The queued job is idempotent: two runs for one request produce one `ready` breakdown, not
  two sets of tasks.
- No API key appears in logs, activity records, or the payload column.
- With OpenAI unreachable, request approval still succeeds and the failure is surfaced.
- Switching `services.openai.model` changes subsequent breakdowns and leaves historical rows
  labelled with the model that actually produced them.

## Out of scope

- AI-generated task assignment (PRD-05 owns assignment; the model proposes work, not people).
- Re-estimation of in-flight tasks.
- Learning from historical accuracy to calibrate future estimates. Worth doing later; it needs
  a corpus of completed estimates versus actuals that does not exist yet.
- Streaming the draft as it generates. The job is short and the result is reviewed as a whole.
- Fine-tuning.

## Open questions

1. Should the model see the PIC's current load so it can propose smaller tasks under pressure?
   Cleaner to keep decomposition independent of capacity and let PRD-05 own timing.
2. Is a per-workspace monthly token budget needed before this ships to many workspaces? At the
   projected volume it is not urgent, but it is cheap to add now and awkward later.
3. Should the requester see the proposed tasks, or only the resulting schedule? Currently only
   the schedule — the task list is IT-internal.
4. Is one key shared by all workspaces, or one key per workspace? Shared is assumed; per
   workspace needs an encrypted credential store, which the integrations feature already has a
   pattern for.
