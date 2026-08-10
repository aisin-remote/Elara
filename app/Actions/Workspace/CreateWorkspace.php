<?php

namespace App\Actions\Workspace;

use App\Enums\WorkspaceMemberStatus;
use App\Enums\WorkspaceRole;
use App\Models\ActivityLog;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateWorkspace
{
    public function handle(User $owner, array $data, ?string $ipAddress = null): Workspace
    {
        return DB::transaction(function () use ($owner, $data, $ipAddress) {
            $workspace = Workspace::create([
                ...$data,
                'owner_id' => $owner->id,
                'settings_json' => ['week_start' => (int) ($data['week_start'] ?? 1)],
            ]);

            $workspace->memberships()->create([
                'user_id' => $owner->id,
                'role' => WorkspaceRole::OWNER,
                'status' => WorkspaceMemberStatus::ACTIVE,
                'joined_at' => now(),
            ]);

            ActivityLog::record($workspace, $workspace, 'workspace.created', $owner, ipAddress: $ipAddress);

            return $workspace;
        });
    }
}
