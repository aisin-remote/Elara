<?php

namespace App\Services;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DepartmentWorkspaceService
{
    /** @param array<string, mixed> $profile */
    public function workspaceFor(array $profile): Workspace
    {
        $departmentId = (int) $profile['department_id'];
        $departmentCode = strtoupper(trim((string) $profile['department_code']));

        if ($workspace = Workspace::where('organization_department_id', $departmentId)->first()) {
            return $this->refreshIdentity($workspace, $departmentId, $departmentCode);
        }

        if (strcasecmp($departmentCode, config('organization.it_department_code')) === 0) {
            $workspace = $this->deliveryWorkspace();

            if ($workspace->organization_department_id
                && $workspace->organization_department_id !== $departmentId) {
                throw new RuntimeException('The configured ITD workspace belongs to another department.');
            }

            return $this->refreshIdentity($workspace, $departmentId, $departmentCode);
        }

        $anchor = $this->deliveryWorkspace();

        try {
            return Workspace::create([
                'owner_id' => $anchor->owner_id,
                'organization_department_id' => $departmentId,
                'organization_department_code' => $departmentCode,
                'name' => $this->name($departmentCode),
                'timezone' => $anchor->timezone,
                'locale' => $anchor->locale,
                'settings_json' => $anchor->settings_json,
            ]);
        } catch (QueryException) {
            return Workspace::where('organization_department_id', $departmentId)->firstOrFail();
        }
    }

    public function deliveryWorkspace(): Workspace
    {
        $publicId = config('organization.workspace_public_id');

        if (blank($publicId)) {
            throw new RuntimeException('ORG_WORKSPACE_PUBLIC_ID is not configured.');
        }

        return Workspace::where('public_id', $publicId)->firstOrFail();
    }

    /** @param array<string, mixed> $profile */
    public function syncMembership(User $user, array $profile, WorkspaceRole $role): Workspace
    {
        $workspace = $this->workspaceFor($profile);

        DB::transaction(function () use ($user, $workspace, $role): void {
            $user->workspaceMemberships()
                ->where('workspace_id', '!=', $workspace->id)
                ->where('status', WorkspaceMemberStatus::ACTIVE->value)
                ->update([
                    'status' => WorkspaceMemberStatus::INACTIVE->value,
                    'updated_at' => now(),
                ]);

            $membership = $workspace->memberships()->firstOrNew(['user_id' => $user->id]);
            $wasActive = $membership->exists && $membership->status === WorkspaceMemberStatus::ACTIVE;
            $membership->fill([
                'role' => $role,
                'status' => WorkspaceMemberStatus::ACTIVE,
                'invited_by' => $workspace->owner_id,
                'joined_at' => $membership->joined_at ?? now(),
            ])->save();

            if (! $wasActive) {
                ActivityLog::record($workspace, $user, 'organization.user_provisioned', $user);
            }
        });

        $user->unsetRelation('workspaceMemberships');

        return $workspace;
    }

    private function refreshIdentity(Workspace $workspace, int $departmentId, string $departmentCode): Workspace
    {
        $workspace->forceFill([
            'organization_department_id' => $departmentId,
            'organization_department_code' => $departmentCode,
            'name' => $this->name($departmentCode),
        ])->save();

        return $workspace;
    }

    private function name(string $departmentCode): string
    {
        return $departmentCode."'s Workspace";
    }
}
