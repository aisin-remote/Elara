<?php

namespace App\Actions\Request;

use App\Enums\FeatureRequestStatus;
use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\FeatureRequest;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use App\Services\OrganizationDirectory;
use Illuminate\Support\Collection;

/**
 * Who hears about a request moving. Supervisors hear about arrivals; the requester hears
 * about every decision on their own request.
 */
class NotifyRequestParticipants
{
    public function __construct(
        private readonly NotificationPreferenceService $notifications,
        private readonly OrganizationDirectory $organization,
    ) {}

    public function handle(FeatureRequest $request, FeatureRequestStatus $from, ?User $actor): void
    {
        $workspace = $request->workspace;
        $requesterWorkspace = $this->organization->departmentWorkspace($request->requester_department_external_id) ?? $workspace;
        $system = $request->system->name;

        if ($request->status === FeatureRequestStatus::PENDING_DEPARTMENT) {
            $departmentWorkspace = $this->organization->departmentWorkspace($request->requester_department_external_id) ?? $workspace;

            foreach ($this->organization->departmentApprovers($departmentWorkspace, $request->requester_department_external_id) as $reviewer) {
                if ($reviewer->id === $actor?->id) {
                    continue;
                }

                $this->notifications->notify(
                    $reviewer,
                    $departmentWorkspace,
                    'feature_request',
                    'Permintaan fitur menunggu approval department',
                    "{$request->requester->name} meminta \"{$request->title}\" untuk {$system}.",
                    route('desk.requests.show', $request),
                    ['request_public_id' => $request->public_id],
                );
            }

            return;
        }

        if ($request->status === FeatureRequestStatus::PENDING_REVIEW) {
            foreach ($this->reviewers($request) as $reviewer) {
                if ($reviewer->id === $actor?->id) {
                    continue;
                }

                $this->notifications->notify(
                    $reviewer,
                    $workspace,
                    'feature_request',
                    $from === FeatureRequestStatus::NEEDS_INFO ? 'Request updated and back for review' : 'New feature request',
                    "{$request->requester->name} asked for \"{$request->title}\" on {$system}.",
                    route('app.approvals.show', [$workspace, $request]),
                    ['request_public_id' => $request->public_id],
                );
            }

            if ($from === FeatureRequestStatus::PENDING_DEPARTMENT && $request->requester_id !== $actor?->id) {
                $this->notifications->notify(
                    $request->requester,
                    $requesterWorkspace,
                    'feature_request',
                    'Disetujui department: '.$request->title,
                    'Permintaan diteruskan ke supervisor ITD untuk approval berikutnya.',
                    route('desk.requests.show', $request),
                    ['request_public_id' => $request->public_id],
                );
            }

            return;
        }

        $decisionNote = $request->needs_info_stage === 'department' || ! $request->reviewed_at
            ? $request->department_decision_note
            : $request->decision_note;

        $message = match ($request->status) {
            FeatureRequestStatus::APPROVED => "Approved. \"{$request->title}\" is queued for scheduling.",
            FeatureRequestStatus::REJECTED => "Not approved: {$decisionNote}",
            FeatureRequestStatus::NEEDS_INFO => "More detail needed: {$decisionNote}",
            FeatureRequestStatus::SCHEDULED => "Scheduled. Work on \"{$request->title}\" is planned.",
            default => null,
        };

        if ($message === null || $request->requester_id === $actor?->id) {
            return;
        }

        $this->notifications->notify(
            $request->requester,
            $requesterWorkspace,
            'feature_request',
            $request->status->label().': '.$request->title,
            $message,
            route('desk.requests.show', $request),
            ['request_public_id' => $request->public_id],
        );
    }

    /** @return Collection<int, User> */
    private function reviewers(FeatureRequest $request)
    {
        return $request->workspace->memberships()
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->whereIn('role', [
                WorkspaceRole::SUPERVISOR->value,
                WorkspaceRole::ADMIN->value,
                WorkspaceRole::OWNER->value,
            ])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();
    }
}
