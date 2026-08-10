<?php

namespace App\Actions\Request;

use App\Enums\ProjectRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ProjectRequest;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\OrganizationDirectory;
use Illuminate\Support\Collection;

/**
 * Two-stage approval means the audience changes with the stage: supervisors while it waits
 * for a meeting or a first signature, managers once it needs the second.
 */
class NotifyProjectRequestParticipants
{
    public function __construct(
        private readonly NotificationPreferenceService $notifications,
        private readonly OrganizationDirectory $organization,
    ) {}

    public function handle(ProjectRequest $request, ProjectRequestStatus $from, ?User $actor): void
    {
        $workspace = $request->workspace;
        $requesterWorkspace = $this->organization->departmentWorkspace($request->requester_department_external_id) ?? $workspace;

        if ($request->status === ProjectRequestStatus::PENDING_DEPARTMENT) {
            $departmentWorkspace = $this->organization->departmentWorkspace($request->requester_department_external_id) ?? $workspace;

            foreach ($this->organization->departmentApprovers($departmentWorkspace, $request->requester_department_external_id) as $reviewer) {
                if ($reviewer->id === $actor?->id) {
                    continue;
                }

                $this->notifications->notify(
                    $reviewer,
                    $departmentWorkspace,
                    'project_request',
                    'Usulan proyek menunggu approval department',
                    "{$request->requester->name} mengusulkan \"{$request->title}\".",
                    route('desk.project-requests.show', $request),
                    ['project_request_public_id' => $request->public_id],
                );
            }

            return;
        }

        $reviewerRoles = match ($request->status) {
            ProjectRequestStatus::PENDING_MEETING, ProjectRequestStatus::PENDING_SPV => [WorkspaceRole::SUPERVISOR],
            ProjectRequestStatus::PENDING_MANAGER => [WorkspaceRole::MANAGER],
            default => [],
        };

        if ($reviewerRoles !== []) {
            $title = match ($request->status) {
                ProjectRequestStatus::PENDING_MEETING => 'New project request — needs a scoping meeting',
                ProjectRequestStatus::PENDING_SPV => 'Project request ready for your signature',
                default => 'Project request needs the second signature',
            };

            foreach ($this->reviewers($request, $reviewerRoles) as $reviewer) {
                if ($reviewer->id === $actor?->id) {
                    continue;
                }

                $this->notifications->notify(
                    $reviewer,
                    $workspace,
                    'project_request',
                    $title,
                    "{$request->requester->name} proposed \"{$request->title}\".",
                    route('app.approvals.projects.show', [$workspace, $request]),
                    ['project_request_public_id' => $request->public_id],
                );
            }

            if ($from === ProjectRequestStatus::PENDING_DEPARTMENT && $request->requester_id !== $actor?->id) {
                $this->notifications->notify(
                    $request->requester,
                    $requesterWorkspace,
                    'project_request',
                    'Disetujui department: '.$request->title,
                    'Usulan diteruskan ke ITD untuk rapat scoping dan approval bertingkat.',
                    route('desk.project-requests.show', $request),
                    ['project_request_public_id' => $request->public_id],
                );
            }

            return;
        }

        $decisionNote = $request->needs_info_stage === 'department'
            ? $request->department_decision_note
            : ($request->manager_note ?: $request->spv_note ?: $request->department_decision_note);

        $message = match ($request->status) {
            ProjectRequestStatus::APPROVED => "Approved by both signatures. \"{$request->title}\" is queued for scheduling.",
            ProjectRequestStatus::REJECTED => 'Not approved: '.$decisionNote,
            ProjectRequestStatus::NEEDS_INFO => 'More detail needed: '.$decisionNote,
            ProjectRequestStatus::SCHEDULED => "Scheduled. Work on \"{$request->title}\" is planned.",
            default => null,
        };

        if ($message === null || $request->requester_id === $actor?->id) {
            return;
        }

        $this->notifications->notify(
            $request->requester,
            $requesterWorkspace,
            'project_request',
            $request->status->label().': '.$request->title,
            $message,
            route('desk.project-requests.show', $request),
            ['project_request_public_id' => $request->public_id],
        );
    }

    /**
     * Admins and owners always see approvals, so they are added to whichever stage is live.
     *
     * @param  array<int, WorkspaceRole>  $roles
     * @return Collection<int, User>
     */
    private function reviewers(ProjectRequest $request, array $roles)
    {
        $values = array_map(fn (WorkspaceRole $role) => $role->value, [
            ...$roles,
            WorkspaceRole::ADMIN,
            WorkspaceRole::OWNER,
        ]);

        return $request->workspace->memberships()
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->whereIn('role', $values)
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();
    }
}
