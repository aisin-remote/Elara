# PRD-09 — Ask AI Phase A

Status: implemented, 2026-08-04. This document records the user's explicit decision to add an AI assistant after the original Orbitra PRD excluded one from v1. It extends, rather than changes, the request-to-delivery flow.

## Outcome

Ask AI is a ChatGPT-style, personal copilot inside each delivery workspace. It answers questions using only Orbitra records the signed-in user may already see and can draft content without changing application data.

## Phase A scope

- Personal conversation history per user and workspace, addressed publicly by ULID.
- Optional project context selected when starting a conversation.
- OpenAI Responses API with server-sent event streaming.
- Permission-aware, read-only tools for assigned tasks, project health, schedule, team workload, requests, and workspace search.
- Drafting and summarisation in the user's language.
- Local model and token usage recorded on assistant messages.
- Provider-side response storage disabled (`store: false`) and a pseudonymous safety identifier sent per user.
- Rate limit of 12 prompts per user/IP per minute on the internal endpoint.

## Access and privacy rules

1. Ask AI is part of the delivery desk; requester-only accounts cannot open or call it.
2. A conversation belongs to one user. Other workspace members, including owners and admins, cannot open it.
3. Every tool begins with the same `visibleTo` and active-membership scopes used by Orbitra screens.
4. A project context cannot be changed after the first prompt and cannot name a project hidden from the user.
5. The OpenAI key remains server-side. It is never returned to the browser or stored in a conversation.
6. Phase A has no create, update, assign, approve, send, archive, or delete tool.

## Acceptance criteria

- The sidebar contains Ask AI and the screen works on desktop and mobile.
- A user can start a chat, optionally select a visible project, see streamed output, stop local streaming, and reopen the chat from history.
- Tool results cannot expose another workspace, another user's private chat, or a project outside the user's project membership.
- Completed answers persist model, input tokens, and output tokens.
- Validation errors and provider failures are visible without exposing the API key or prompt payload.
- PHPUnit, Pint, and the production asset build pass at the phase checkpoint.

## Deferred

- Phase B: private attachment/file knowledge, citations over file content, and saved reusable prompts.
- Phase C: proposed mutations with explicit confirmation, approval gates, and an audit trail.
- Shared/team AI conversations, admin transcript access, voice, image generation, and autonomous agents.
