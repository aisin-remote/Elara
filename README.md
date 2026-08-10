# Orbitra

Orbitra is an original project-management SaaS built as a Laravel 10 monolith with Blade, Tailwind CSS, Alpine.js, MySQL 8, and manual session authentication.

The product uses the Flowza reference only for information hierarchy and interaction patterns. Orbitra does not copy Flowza branding, assets, text, source code, or visual identity.

## Current checkpoint

PostgreSQL organization-directory integration is complete (2026-08-09):

- Orbitra reads the external `public` schema through a second, read-only-in-application
  connection. User identity is matched by normalized email and resolves job rank, division,
  department, and section without copying the corporate directory into MySQL.
- Supported corporate levels are grouped exactly as `MGR/COOR`, `SPV/SCH`, and
  `LDR/STF/SN STF`. ITD levels map to Orbitra delivery roles; non-IT users remain requesters.
- A non-IT requester below MGR/COOR enters department approval first. A non-IT MGR/COOR
  bypasses that gate. Feature requests then need ITD SPV approval; project requests retain
  the scoping meeting followed by ITD SPV and ITD MGR approval.
- Non-IT MGR/COOR users receive a requester-side **Approvals** menu for their department.
  Feature requests are readable by requesters in the same department; project requests stay
  private because their business case may be sensitive.
- Every request stores an organization snapshot so later directory changes do not rewrite
  approval history. Missing, ambiguous, unsupported, or multi-department identities fail
  closed. Submission is also refused when no department approver has active workspace access.

Requester live monitoring is complete: feature and project request details now show the
current stage, task/checklist progress, blocked count, PIC, planned window, validation action,
and the full request-to-delivery timeline. The component refreshes its policy-scoped payload
every ten seconds without exposing internal task names or delivery-board access.

Phase 12b is complete (attachments on both request types, PRD-03 and PRD-04) — the last phase
in the delivery order:

- One polymorphic owner on `files`, added once for both request types, which is why PRD-03
  deferred it rather than solving it twice.
- `project_id` is left null on purpose: hanging a requester's draft screenshots off the target
  system would file them into that system's library for the whole team to browse.
- `FilePolicy` now answers to the request an attachment belongs to. Without that branch it fell
  through to `WorkspacePolicy::view`, and every member — including other requesters — could
  read one person's evidence.
- Attaching follows the same rule as editing the request: your own, and only while it is open.
  Removing is the uploader's alone, and stops once a decision has been made on it — evidence
  someone approved against should not disappear afterwards.
- Approvers see the attachments beside the decision form, which is the point of attaching them.
- **Pending:** run `php artisan migrate` for `2026_08_03_300000_add_attachable_to_files_table`.

The two desks are now closed against each other (completes PRD-01's role split):

- `EnsureRequestDeskAccess` mirrors the Phase 8 gate: `/desk` is refused to the delivery team,
  exactly as `/app` is refused to requesters. A deny-list on the whole group, so a desk route
  added later is closed by default rather than open until somebody remembers.
- Raising a request is a requester's job. IT creates work on the board directly; a request
  filed by IT would enter an approval queue whose purpose is deciding whether IT should do it.
- Blocked only when *every* membership is on the other side, so a requester who also joins an
  IT team in another workspace keeps both desks.
- Fixed alongside: the Phase 15b final-warning and takedown notifications pointed every
  recipient at the requester's desk, including the PIC and supervisor. Under the new gate two
  of the three recipients would have followed that link into a 403.

Phase 15b is complete (the sweeper and takedown, see `docs/prd/PRD-07-validation-checkpoints.md`):

- `orbitra:sweep-validations` runs hourly: a reminder at the midpoint, a final warning a day
  out copied to the PIC and supervisor, and expiry after that. Idempotent — the stamp is
  written before the notification, so a second run in the same hour sends nothing.
- Once the final warning has gone out the midpoint reminder no longer fires. A nudge arriving
  after the last warning reads as a step backwards.
- Expiry archives the feature (or project) and its **unfinished** tasks. Completed work keeps
  its record: a takedown removes future work, not history.
- Capacity is released, never handed over. `orbitra:drain-request-queue` absorbs it on its own
  schedule — a direct hand-off would be a second mechanism that has to agree with the first.
- Other open checkpoints on the same request are cancelled, not left asking about work that no
  longer exists.

Phase 15a is complete (validation checkpoints, see `docs/prd/PRD-07-validation-checkpoints.md`):

- Completing a task flagged `requires_user_validation` opens a checkpoint for the requester.
  The hook sits in both completion paths — the board drag and the task form — because a rule
  enforced on only one of them looks enforced while depending on which button was pressed.
- `expires_at` is stamped at opening from the window in force then, and never recomputed:
  shortening the window affects the next checkpoint, never one already counting down.
- The requester's **Validations** page states the deadline as a date and spells out the
  consequence. A deadline nobody knows about is not a deadline.
- Approve lets the work stand. Request changes reopens the task for the PIC and **stops** the
  countdown — the requester answered, so the deadline is no longer theirs.
- Only the requester may answer. A PIC confirming their own work is the loophole a checkpoint
  exists to close, and it is refused server-side.
- Also fixed here: `requires_user_validation` was collected and validated in Phase 14b but
  never written to the task it described, so nothing could have triggered.
- **Pending:** run `php artisan migrate` for `2026_08_03_200000_create_validation_checkpoints_table`.
- Deferred to 15b: `orbitra:sweep-validations` — the midpoint reminder, the final warning, and
  expiry with takedown, archive, and capacity release for the queue to absorb.

Phase 14b is complete (review and acceptance, see `docs/prd/PRD-06-ai-task-breakdown.md`):

- The review panel sits on both approval screens: editable titles, descriptions, estimates,
  and validation flags, with a running total and a projected finish date.
- The finish date comes from `CapacityPlanner::layOut` over an endpoint, not from arithmetic
  in the browser — weekends, holidays, and leave are the planner's job, and a second
  implementation of them would drift from the first.
- Accepting creates the `Feature` and its tasks in one transaction, assigned to the PIC the
  queue picked, laid out across working days with the same rule that reserved the window. The
  reviewer's edited estimates win over the model's and are written back to the request.
- Acceptance releases the capacity reservation, so the same effort is never counted twice.
- Regenerate takes a note for the model; discard keeps the draft readable rather than deleting
  it. Neither is possible once a plan has been accepted.
- The model is overridable per workspace under Settings → Master data → Request rules.
- A ready plan announces itself: the PIC is notified, it appears under "Plans waiting for
  acceptance" at the top of Approvals, and it counts in the sidebar badge. Without this the
  human step PRD-06 depends on was invisible — the plan sat under "Recent decisions", which
  reads like an archive.
- The review panel spans the page rather than sitting in the 360px rail beside it. An editable
  task list needs the width; widening the fields inside the rail only widens the rail.
- Not built: task dependency links. `depends_on` is shown as ordering only, because no
  dependency table exists and PRD-06 does not ask for one. Validation checkpoints are PRD-07.

Phase 14a is complete (AI breakdown generation, see `docs/prd/PRD-06-ai-task-breakdown.md`):

- `task_breakdowns` stores one proposed task list per request, with `provider`, the exact
  `model` that produced it, and token counts — so cost per request is answerable without
  guessing, and history stays labelled when the model changes.
- `TaskBreakdownGenerator` with one OpenAI implementation calling the Responses API through
  Laravel's HTTP client. No SDK. Output is constrained by a strict JSON schema.
- Refusals, API errors, timeouts, and a missing key all land in `failed` with the reason
  visible. Approval still succeeds and manual entry is never blocked.
- `GenerateTaskBreakdown` is dispatched after the approval commits, and is idempotent: a
  ready or accepted draft is never replaced, and a retry reuses the failed row.
- **Pending:** run `php artisan migrate` for `2026_08_03_100000_create_task_breakdowns_table`,
  and set `OPENAI_API_KEY` plus `OPENAI_MODEL` in `.env`.
- Deferred to 14b: the review screen, acceptance (which creates the `Feature` and its tasks),
  releasing the capacity reservation `CapacityPlanner` holds for a scheduled request, and the
  model picker in Master data. No task is written to the database until that lands.

Phase 13 is complete (capacity planner and the queue, see `docs/prd/PRD-05-capacity-scheduling.md`):

- `CapacityPlanner` answers "who is free, and when" from committed effort per working day —
  not calendar free/busy. Meetings are subtracted as overhead. Deterministic by construction.
- Member capacity, leave, and workspace holidays are edited under Settings → Master data,
  alongside the three tuning numbers in `WorkspaceSettings`.
- `orbitra:drain-request-queue` runs hourly, oldest approval first, and leaves anything that
  does not fit inside the horizon in the queue rather than forcing a date nobody can meet.
- An approval now collects an effort estimate, because a request without one is a queue entry
  the planner can never drain.

Phase 12a is complete (project request intake, see `docs/prd/PRD-04-project-request-intake.md`):

- `project_requests` with the four required business-case fields, the eleven-state machine, and
  `TransitionProjectRequest` as the only way through it.
- The scoping meeting is a real gate: the first signature is refused until the meeting is
  recorded as held, and the refusal lives in the Action, so a direct transition cannot skip it.
  Booking reuses `schedule_events`, inviting the requester and the supervisor.
- Sequential signatures, supervisor then manager, and the second must come from a different
  person — enforced server-side even when one user holds both roles.
- Approval creates exactly one delivery project; a repeat approval is refused by the state
  machine rather than creating a second. The requester is deliberately not a project member.
- Approvals queue and the sidebar badge now cover both streams; the requester desk offers
  both request types and shows a three-step progress trail for proposals.
- Deferred to 12b: attachments for both request types. `files.project_id` is already nullable,
  so the schema is cheap, but the upload flow, policy, and desk UI are not — and they should be
  built once for feature and project requests together.

Phase 11 is complete (feature request intake, see `docs/prd/PRD-03-feature-request-intake.md`):

- `feature_requests` with the nine-state machine from the PRD. Every transition goes through
  `TransitionFeatureRequest`, which refuses illegal moves, requires a note on reject and
  needs-info, writes an activity record, and notifies — so a new entry point cannot invent a
  shortcut around any of it.
- Requester desk: submit a request against a system, follow its stage, answer a needs-info
  question in place, withdraw while nobody has acted.
- Approvals queue for supervisor, manager, admin, owner, urgent first then oldest, with the
  full problem and outcome text inline so a decision needs no click-through. Only supervisors,
  admins, and owners may decide — managers watch, because they are the second signature on
  project requests (PRD-04) and mixing the two blurs the streams.
- A system with no PIC cannot receive requests, and a thin request is refused by validation
  rather than accepted and rewritten later.
- New notification event `feature_request`, honouring the existing per-channel preferences.
- **Pending:** run `php artisan migrate` for `2026_07_31_300000_create_feature_requests_table`.
- Deferred: attachments. `project_files` is keyed to `project_id`, so request attachments
  would leak into the target system's file library; PRD-04 needs them too, so the polymorphic
  version gets built once, for both.

Phase 10 is complete (system catalog and Feature menu, see `docs/prd/PRD-02-system-catalog-pic.md`):

- `projects.type` splits delivery projects from standing systems, so a system reuses the whole
  existing stack — statuses, board, task list, timeline, files, progress, policies — instead of
  forking a second task pipeline.
- `features` table and `tasks.feature_id`: a feature groups the tasks one approved request will
  produce, while its tasks keep `project_id` so nothing else had to change.
- Feature menu: cards per system, then features with their tasks, plus a Maintenance section for
  system tasks that belong to no feature.
- Systems master (deferred from Phase 9a) with PIC assignment. Changing the PIC demotes the
  previous holder; a requester can never be one; a system with live features cannot be archived.
- Audited every surface that lists projects. Projects-only: index, archive list, create page,
  sidebar quick access, dashboard timeline, dashboard add-task picker, global search. Both types
  grouped under optgroups: Task List and Performance filters, because system tasks appear in
  those lists and hiding them from the filter would leave unfilterable rows.
- Sidebar highlighting follows the bound record, not the route name: a system's board rides
  `app.projects.*` but lights up Features.

Phase 9a is complete (master data, see `docs/prd/PRD-08-master-data.md`):

- Settings → Master data, gated by `WorkspacePolicy::manageSettings`: owner/admin everywhere,
  plus every contributing ITD role in the department delivery workspace.
- Task categories completed: the feature previously had a create endpoint and nothing else.
  Rename, archive, and restore, with archive blocked while tasks still reference the category
  unless a replacement is chosen.
- Task status template: the four statuses copied into every new project were hard-coded in
  `TaskStatus::createDefaultsFor`. A workspace can now define its own; the built-in set stays
  as the fallback when no active template exists.
- Help articles: `support_articles` was seeder-only, so editing help content needed a deploy.
  Create, edit, publish, archive, with the slug derived from the title when left blank.
- Reference rows archive rather than delete, and archived rows leave every picker while staying
  readable on historical records.
- Deferred by dependency: Systems master (needs `projects.type` from Phase 10), and capacity,
  holidays, and request rules (Phase 9b, alongside the planner that reads them).
- **Pending:** run `php artisan migrate` for `2026_07_31_100000_create_master_data_tables`.

Phase 8 is complete (request-to-delivery layer, see `docs/prd/`):

- Workspace roles extended with manager, supervisor, and requester; `WorkspaceRole` now answers
  `canAccessDeliveryDesk()`, `canContribute()`, and `assignable()` instead of policies testing
  for a specific role.
- Every policy audited for the new roles. The four checks that read "is not a viewer"
  (conversations create/send, file upload, schedule event create, task update) now ask
  `canContribute()` — as written they would have granted a requester write access.
- Requester desk at `/desk` with its own layout, and a deny-gate on the whole `/app` group so
  delivery routes added later are closed to requesters by default.
- Role-aware post-login redirect, including the two-factor path and the already-authenticated
  redirect; requesters bypass `intended()` so a stored `/app` URL cannot bounce them to a 403.
- Invite and member-role forms plus their validation now read from `WorkspaceRole::assignable()`.
- Demo dataset covers all seven roles.

Phase 7 is complete:

- Laravel 10.50.2 on PHP 8.2.
- Manual register, login, remember-me, logout, password reset, optional email verification, and password confirmation.
- Login throttling by normalized email and IP.
- Session regeneration after login/register and invalidation on logout.
- ULID public user identifiers, including verification URLs.
- Database-backed sessions, queues, and notifications.
- Responsive Blade application shell with accessible forms and light/dark/system themes.
- Workspace onboarding, switching, settings, isolated membership, invitations, role changes, deactivation, and ownership transfer.
- Project creation, editing with optimistic locking, member assignment, role-based access, archive, restore, search, and filtering.
- Session-authenticated, CSRF-protected mutation endpoints under `/api/internal` with Form Requests, Policies, and API Resources.
- Workspace-scoped activity logs and ULID public routing for every exposed domain identifier.
- Workspace-wide and project task lists with search, priority filtering, pagination, grouped status tables, bulk actions, and archive/restore.
- Kanban board with project-scoped workflow statuses, native drag-and-drop, sparse ordering, idempotent move operations, and optimistic version conflict handling.
- Task create, edit, duplicate, detail, assignees, categories, checklist, comments, due dates, priorities, and completion tracking.
- Private task attachments with randomized storage paths and policy-authorized download/delete endpoints.
- Project month calendar backed by the same task records, with month navigation, overflow handling, mobile agenda fallback, and authorized date drag rescheduling.
- Workspace weekly schedule with timezone-aware events, attendees, meeting links, create/edit/move/delete actions, optimistic version checks, and overlap warnings.
- Private project file library with upload, image/PDF preview, download, search, MIME/uploader/date filters, rename, task attachment, pagination, and storage cleanup.
- Real task-derived project progress; the interface never fabricates task statistics.
- Permission-aware dashboard metrics for total, in-progress, overdue, and completed tasks with equivalent previous-period comparison.
- Workspace-timezone date boundaries, task performance trend, member due-date heatmap, recent activity, upcoming meetings, and status/priority distribution.
- Performance reporting with active-project KPI, completion and overdue rates, average completion time, member workload, and configurable same-status bottleneck detection.
- Working date/project/member/status filters plus authorized CSV and PDF exports that preserve the active filter set.
- Chart.js production charts and DomPDF report generation, both integrated into the Laravel monolith.
- Direct, group, and project conversations with participant-only access, search, unread counts, cursor-paginated messages, private attachments, emoji, reactions, typing presence, read state, and a configurable own-message edit/delete window.
- Persist-before-broadcast messaging events on authorized presence channels, Laravel Echo/Pusher support, and an automatic polling fallback when local broadcast credentials are not enabled.
- Database notification center with unread badge, mark-one/mark-all behavior, queued mail and broadcast delivery, browser Web Push subscriptions, and per-workspace channel preferences.
- Task assignment/status, comment, deadline, project update, team activity, and new-message notification hooks with an hourly deadline reminder command.
- Profile management with private avatars, personal details, locale/timezone, persisted light/dark/system theme, password-confirmed email changes, and optional re-verification.
- Current-password protected password changes, TOTP two-factor enrollment and login challenge, one-time recovery codes, database session revocation, and a security activity timeline.
- Unlimited workspace access: members, delivery projects, application storage quota, integrations, and report exports have no Orbitra package limit. Billing pages and subscription mutations are disabled.
- Owner-only Slack, Google Drive, GitHub, and Zoom OAuth connections with signed state, encrypted/refreshable tokens, provider revocation, connection health, and real provider actions.
- Searchable published knowledge base, FAQ pages, and workspace-scoped support tickets with permission and rate-limit enforcement.
- Complete original Orbitra marketing site with product preview, features, workflow, integrations, pricing, team stories, FAQ, legal pages, and responsive navigation.
- Permission-aware global search across accessible projects, tasks, private file metadata, active members, and conversations, with server-side pagination and safe highlighting.
- Keyboard-safe mobile navigation, skip links, reduced-motion behavior, offline feedback, submit progress, and dedicated 403/404/419/429/500/503 states.
- Local-only, idempotent Product Studio demo dataset with four roles, two projects, 20 varied tasks, schedules, a real private fixture, messages, notifications, and activity.
- Trusted-host validation, response security headers, and explicit throttling for account, invitation, messaging, upload, search, and integration boundaries.
- SQLite in-memory test environment while MySQL remains the development and production target.

## Source of truth

- `../isma/docs/flowza-project/PRD_Orbitra_Project_Management_SaaS.md`
- `../isma/docs/flowza-project/Prompt_Teknis_Orbitra_Laravel10_Blade.txt`

Read both files completely before planning or changing Orbitra. Implement phases in their documented order.

## Requirements

- PHP 8.2 with `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, `mbstring`, `openssl`, and `fileinfo`.
- Composer 2.
- MySQL 8.
- Node.js 20+ and npm.

Do not install Breeze, Fortify, Jetstream, Inertia, React, Vue, Livewire, shadcn, or a SPA framework.

## Local setup

Create an empty MySQL 8 database named `orbitra`, then run:

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

On PowerShell, replace `cp` with:

```powershell
Copy-Item .env.example .env
```

Update `DB_USERNAME` and `DB_PASSWORD` in `.env` before migrating. Run the database queue worker in another terminal:

```bash
php artisan queue:work
```

## Important environment variables

```dotenv
APP_NAME=Orbitra
APP_URL=http://localhost:8000
DB_CONNECTION=mysql
DB_DATABASE=orbitra
SESSION_DRIVER=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
BROADCAST_DRIVER=log
MAIL_MAILER=log
ORBITRA_EMAIL_VERIFICATION=false
ORBITRA_BOTTLENECK_DAYS=7
ORBITRA_MESSAGE_EDIT_WINDOW_MINUTES=15
ORBITRA_TRIAL_DAYS=14
ORBITRA_CONTACT_SALES_URL=
ORBITRA_LINKEDIN_URL=
ORBITRA_X_URL=
ORBITRA_GITHUB_URL=
ORG_DB_HOST=127.0.0.1
ORG_DB_PORT=5432
ORG_DB_DATABASE=postgres
ORG_DB_SCHEMA=public
ORG_DB_USERNAME=postgres
ORG_DB_PASSWORD=
ORG_DB_SSLMODE=prefer
ORG_DB_REQUIRED=true
ORG_IT_DEPARTMENT_CODE=ITD
ORG_JIT_AUTH=false
ORG_WORKSPACE_PUBLIC_ID=
```

`public` is the PostgreSQL schema, while `ORG_DB_DATABASE` is the database name. The local
directory inspected for this integration is in the `postgres` database. Orbitra only issues
`SELECT` queries on this connection; use a database account with read-only grants outside
local development. Corporate email must match the Orbitra login email. Set
`ORG_DB_REQUIRED=false` only for isolated development or tests that intentionally have no
directory service. Set `ORG_JIT_AUTH=true` with the target workspace public ID to enable
single-form company login: Orbitra verifies the PostgreSQL bcrypt password, then creates or
updates the local user bridge and department workspace membership automatically. The workspace
named by `ORG_WORKSPACE_PUBLIC_ID` is the ITD delivery workspace. Each PostgreSQL department
gets one home workspace named `<DEPARTMENT CODE>'s Workspace`; non-IT requests are submitted
from that home workspace but stored and delivered once in ITD. Company-managed users
change and recover their password through the corporate account service, not Orbitra.
When a company user is removed from PostgreSQL, the next active-session sync or
`php artisan organization:sync-workspaces` purges their local bridge and required child data,
then signs out an affected active session. If a local user row was removed manually while
foreign-key checks were disabled, repair the resulting records with
`php artisan orbitra:prune-orphaned-data --force`.

Set `ORBITRA_EMAIL_VERIFICATION=true` to require signed verification links. Configure a real mail transport before enabling it outside local development.
`ORBITRA_BOTTLENECK_DAYS` controls how long a task may remain in the same in-progress status before the performance report flags it.
`ORBITRA_MESSAGE_EDIT_WINDOW_MINUTES` controls how long senders may edit or delete their own messages.

Local development works with `BROADCAST_DRIVER=log` and the UI polling fallback. For realtime delivery, set `BROADCAST_DRIVER=pusher`, configure `PUSHER_APP_ID`, `PUSHER_APP_KEY`, `PUSHER_APP_SECRET`, and their matching `VITE_PUSHER_*` values, then rebuild the frontend. Web Push also requires stable `VAPID_SUBJECT`, `VAPID_PUBLIC_KEY`, and `VAPID_PRIVATE_KEY` values; never rotate them without planning to invalidate existing browser subscriptions.

## Manual authentication architecture

Auth routes live in `routes/auth.php`. Controllers are under `app/Http/Controllers/Auth`, validation and normalization live under `app/Http/Requests/Auth`, and Laravel's password broker owns reset tokens for local accounts. Authentication uses the `web` guard and session cookies; there is no token API or auth starter kit. When company login is enabled, the same form performs just-in-time provisioning from PostgreSQL; registration is hidden, corporate remember tokens are disabled, and local owner/admin accounts remain protected local credentials.

Passwords require at least 12 characters with mixed case, a number, and a symbol. The backend rule is authoritative.

## Database and storage

Phase 0 migrations create users, password-reset tokens, sessions, jobs, failed jobs, and notifications. Phase 1 adds workspaces, workspace members and invitations, projects and project members, and activity logs. Phase 2 adds task workflows, categories, tasks, assignees, checklists, watchers, comments, private files, and idempotent move operations. Phase 3 adds schedule events and attendees while extending private files to full project file management. Phase 4 records task status-entry timestamps for exact bottleneck age. Phase 5 adds conversations, participants, messages, attachment and reaction pivots, notification preferences, and Web Push subscriptions. Phase 6 adds Cashier subscription data, security events, integration connections/resources, support articles/tickets, and idempotent webhook receipts. Migrations are reversible. The local disk remains private; files and profile avatars are streamed through authorized controllers rather than exposed by public paths.

## Demo data

`DatabaseSeeder` loads the complete demo only when `APP_ENV` is `local` or `development`. `DemoSeeder` throws before writing anything in production. Run it explicitly with:

```bash
php artisan db:seed --class=DemoSeeder
```

All demo accounts use the password `password`:

- `owner@example.com` — workspace Owner, project Manager.
- `manager@example.com` — workspace Admin, project Manager.
- `member@example.com` — workspace Member, project Member.
- `viewer@example.com` — workspace Viewer, project Viewer.
- `supervisor@example.com` — ITD SPV; approves features and signs projects first.
- `lead@example.com` — ITD MGR; signs projects second.
- `requester@example.com` — Finance STF; requests need department approval.
- `department-head@example.com` — Finance MGR; approves its department and bypasses that
  gate for its own requests.

With company login enabled, the ITD demo users belong to `ITD's Workspace`; the Finance
requester and department head belong to `FIN's Workspace`. Corporate provisioning keeps only
the workspace matching the current PostgreSQL department active for each user.

When the organization connection is required, `DemoSeeder` also writes these five synthetic
directory profiles (`company = ORBITRA DEMO`) to PostgreSQL. It refuses to overwrite an
existing non-demo row with the same email and never runs outside local/development. Their
company-directory password is also `password`, and their local rows are linked automatically.

The demo creates the Product Studio workspace, Website Redesign and Mobile App Development projects, 20 meaningful tasks, current-week events, messages, notifications, activity, support content, and `storage/app/private/demo/orbitra-product-brief.txt`.

## Queue, broadcast, and external services

- Queue: database driver.
- Broadcast: log/polling locally, with configured Pusher/Echo private and presence channels for realtime messaging and notifications.
- Slack, Google Drive, GitHub, and Zoom: create OAuth applications using the callback URLs in `.env.example`, add the matching client credentials, and keep all redirect URLs identical to the provider configuration. A provider card remains visibly unavailable until credentials exist.
- Web push/VAPID: Laravel WebPush subscriptions and queued notification channel are implemented in Phase 5.

## Quality commands

```bash
php artisan test
vendor/bin/pint --test
npm run build
```

Tests use SQLite in memory and never call `migrate:fresh` against user data.

## Deployment checklist

1. Use PHP 8.2+, MySQL 8, HTTPS, and production mail, queue, broadcast, and OAuth credentials.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY`, the canonical HTTPS `APP_URL`, and `SESSION_SECURE_COOKIE=true`.
3. Back up MySQL and `storage/app/private` before changing code or schema. Never run `migrate:fresh` against user data.
4. Run `composer install --no-dev --optimize-autoloader` and `npm install && npm run build` from the reviewed release.
5. Put the app in maintenance mode, run `php artisan migrate --force`, then `php artisan optimize` and `php artisan queue:restart`.
6. Run supervised `php artisan queue:work --tries=3 --timeout=90`; invoke `php artisan schedule:run` every minute.
7. Restore traffic, verify `/`, login, one authorized workspace, queue processing, storage download, and provider webhooks, then monitor application and failed-job logs.

Create matching database and private-storage backups, for example:

```bash
mysqldump --single-transaction --routines --triggers -u orbitra -p orbitra > orbitra.sql
tar -czf orbitra-private-storage.tar.gz storage/app/private
```

Test restoration away from production before it is needed:

```bash
mysql -u orbitra -p orbitra_restore < orbitra.sql
tar -xzf orbitra-private-storage.tar.gz
php artisan migrate:status
```

The expanded zero-downtime, worker, webhook, backup, restore, and rollback procedure is in `docs/DEPLOYMENT.md`.

## Known limitations

- The configured local database server currently reports MySQL 5.7.40. Migration cycles succeed there, but target-environment proof still requires MySQL 8 as specified by the PRD.
- Live OAuth, mail, broadcast, and Web Push acceptance requires credentials and provider-side callback/webhook configuration; automated tests use fakes and never send external requests.
- The current frontend does not include a generated npm lockfile because the local registry request timed out; production CI should generate and review one before switching from `npm install` to reproducible `npm ci` installs.
- Orbitra is responsive web software. Native mobile, offline-first synchronization, AI assistance, WebRTC calling, business-suite modules, workflow automation, and a public developer API are outside version 1. The dashboard includes a read-only, permission-aware project Gantt timeline.
