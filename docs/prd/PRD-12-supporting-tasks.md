# PRD-12 — Supporting Tasks

## Purpose

Supporting records operational IT work that does not belong to a project, system, or approved feature. Examples include preparing a PowerPoint, repairing a printer, installing software, or resolving network access.

## Users and permissions

- Active delivery-desk members can view all supporting tasks in their department workspace.
- Workspace roles that can contribute may create, update, complete, and archive supporting tasks.
- Viewer and requester roles cannot mutate supporting tasks.
- Assignees must be active contributing members of the same workspace.
- Cross-workspace access returns 404 through scoped route binding.

## Fields

- Title and optional description.
- Category: presentation/document, hardware/device, software/account, network, or other.
- Priority: low, medium, high, or urgent.
- Status: to do, in progress, completed, or cancelled.
- Optional assignee and due date.
- Creator and completion timestamp are recorded automatically.

## Flow

1. A delivery member opens **Supporting** from the Delivery navigation group.
2. They register the work without selecting a project, system, or feature.
3. The team filters the list by status, category, or assignee.
4. A contributor updates its assignment, priority, status, or due date.
5. Setting the status to Completed records `completed_at`; moving away from Completed clears it.
6. Archive soft-deletes the item while retaining its activity history.

## Deliberate boundary

Supporting tasks are separate from project tasks, so they do not change project progress, feature progress, or project-specific workflow. Dashboard/report integration can be added later if product reporting should combine delivery and operational work.
