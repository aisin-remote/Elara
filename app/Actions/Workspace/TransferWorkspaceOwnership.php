<?php

namespace App\Actions\Workspace;

use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TransferWorkspaceOwnership
{
    public function handle(Workspace $workspace, User $currentOwner, string $membershipPublicId, ?string $ipAddress = null): Workspace
    {
        return DB::transaction(function () use ($workspace, $currentOwner, $membershipPublicId, $ipAddress) {
            $newOwnerMembership = $workspace->memberships()
                ->active()
                ->where('public_id', $membershipPublicId)
                ->lockForUpdate()
                ->first();

            if (! $newOwnerMembership || $newOwnerMembership->user_id === $currentOwner->id) {
                throw ValidationException::withMessages(['member' => 'Choose another active workspace member.']);
            }

            $workspace->memberships()->where('user_id', $currentOwner->id)->update(['role' => WorkspaceRole::ADMIN->value]);
            $newOwnerMembership->update(['role' => WorkspaceRole::OWNER]);
            $workspace->update(['owner_id' => $newOwnerMembership->user_id]);

            ActivityLog::record($workspace, $workspace, 'workspace.ownership_transferred', $currentOwner, ['new_owner_public_id' => $newOwnerMembership->user->public_id], $ipAddress);

            return $workspace->fresh();
        });
    }
}
