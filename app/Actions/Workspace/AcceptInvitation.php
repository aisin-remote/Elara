<?php

namespace App\Actions\Workspace;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Services\NotificationPreferenceService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcceptInvitation
{
    public function __construct(private readonly NotificationPreferenceService $notifications) {}

    public function handle(User $user, string $token, ?string $ipAddress = null): Workspace
    {
        $workspace = DB::transaction(function () use ($user, $token, $ipAddress) {
            $invitation = $this->pendingInvitation($token);
            $this->ensureIntendedUser($user, $invitation);

            if ($invitation->expires_at->isPast()) {
                throw ValidationException::withMessages(['invitation' => 'This invitation has expired.']);
            }

            $membership = $invitation->workspace->memberships()->firstOrNew(['user_id' => $user->id]);
            $membership->fill([
                'role' => $membership->exists ? $membership->role : $invitation->role,
                'status' => WorkspaceMemberStatus::ACTIVE,
                'invited_by' => $invitation->invited_by,
                'joined_at' => $membership->joined_at ?? now(),
            ])->save();

            $invitation->update(['accepted_at' => now()]);
            ActivityLog::record($invitation->workspace, $membership, 'workspace.invitation_accepted', $user, ipAddress: $ipAddress);

            return $invitation->workspace;
        });

        $workspace->memberships()->active()->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])->with('user')->get()
            ->pluck('user')->filter()->where('id', '!=', $user->id)->each(fn (User $recipient) => $this->notifications->notify(
                $recipient,
                $workspace,
                'team_activity',
                'New team member',
                $user->name.' joined '.$workspace->name.'.',
                route('app.workspaces.team', $workspace),
                ['member_public_id' => $user->public_id],
            ));

        return $workspace;
    }

    public function reject(User $user, string $token, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($user, $token, $ipAddress) {
            $invitation = $this->pendingInvitation($token);
            $this->ensureIntendedUser($user, $invitation);
            $invitation->update(['rejected_at' => now()]);
            ActivityLog::record($invitation->workspace, $invitation, 'workspace.invitation_rejected', $user, ipAddress: $ipAddress);
        });
    }

    public function find(string $token): WorkspaceInvitation
    {
        return WorkspaceInvitation::query()
            ->with(['workspace', 'inviter'])
            ->where('token_hash', hash('sha256', $token))
            ->firstOrFail();
    }

    private function pendingInvitation(string $token): WorkspaceInvitation
    {
        $invitation = WorkspaceInvitation::query()
            ->with('workspace')
            ->where('token_hash', hash('sha256', $token))
            ->lockForUpdate()
            ->firstOrFail();

        if ($invitation->accepted_at || $invitation->rejected_at) {
            throw ValidationException::withMessages(['invitation' => 'This invitation is no longer available.']);
        }

        return $invitation;
    }

    private function ensureIntendedUser(User $user, WorkspaceInvitation $invitation): void
    {
        if (! hash_equals(Str::lower($user->email), Str::lower($invitation->email))) {
            throw new AuthorizationException('This invitation belongs to another email address.');
        }
    }
}
