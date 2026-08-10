<?php

namespace App\Actions\Workspace;

use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use App\Services\PlanEntitlementService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class InviteWorkspaceMember
{
    public function __construct(private readonly PlanEntitlementService $entitlements) {}

    public function handle(Workspace $workspace, User $inviter, string $email, string $role, ?string $ipAddress = null): WorkspaceInvitation
    {
        $email = Str::lower(trim($email));

        if ($workspace->memberships()->active()->whereHas('user', fn ($query) => $query->where('email', $email))->exists()) {
            throw ValidationException::withMessages(['email' => 'This person is already an active workspace member.']);
        }

        $hasPendingInvitation = $workspace->invitations()
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->whereNull('rejected_at')
            ->where('expires_at', '>', now())
            ->exists();
        if (! $hasPendingInvitation) {
            $this->entitlements->assertCanInviteMember($workspace);
        }

        $token = Str::random(64);

        $invitation = DB::transaction(function () use ($workspace, $inviter, $email, $role, $token, $ipAddress) {
            $invitation = $workspace->invitations()
                ->where('email', $email)
                ->whereNull('accepted_at')
                ->whereNull('rejected_at')
                ->latest('id')
                ->first() ?? new WorkspaceInvitation(['workspace_id' => $workspace->id, 'email' => $email]);

            $invitation->fill([
                'role' => $role,
                'invited_by' => $inviter->id,
                'token_hash' => hash('sha256', $token),
                'expires_at' => now()->addDays(7),
            ])->save();

            ActivityLog::record($workspace, $invitation, 'workspace.invitation_sent', $inviter, ['email' => $email, 'role' => $role], $ipAddress);

            return $invitation;
        });

        Notification::route('mail', $email)->notify(new WorkspaceInvitationNotification(
            $workspace->name,
            $inviter->name,
            $token,
            $invitation->expires_at->toDayDateTimeString(),
        ));

        return $invitation;
    }
}
