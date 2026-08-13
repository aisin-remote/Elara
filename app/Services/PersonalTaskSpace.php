<?php

namespace App\Services;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\WorkspaceMemberStatus;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class PersonalTaskSpace
{
    public function for(Workspace $workspace, User $user): Project
    {
        abort_unless($workspace->memberships()
            ->where('user_id', $user->id)
            ->where('status', WorkspaceMemberStatus::ACTIVE->value)
            ->exists(), 404);

        return DB::transaction(function () use ($workspace, $user): Project {
            Workspace::query()->whereKey($workspace->id)->lockForUpdate()->firstOrFail();

            $space = Project::query()
                ->where('workspace_id', $workspace->id)
                ->where('owner_id', $user->id)
                ->where('type', ProjectType::PERSONAL->value)
                ->first();

            if ($space) {
                return $space;
            }

            $space = $workspace->projects()->create([
                'owner_id' => $user->id,
                'name' => 'Personal tasks',
                'type' => ProjectType::PERSONAL,
                'description' => null,
                'color' => '#6366f1',
                'status' => ProjectStatus::ACTIVE,
                'task_fields_json' => [
                    'assignees' => ['name' => 'Assignee', 'visible' => false],
                ],
            ]);
            $space->memberships()->create([
                'user_id' => $user->id,
                'role' => ProjectMemberRole::MANAGER,
            ]);
            TaskStatus::createDefaultsFor($space);

            return $space;
        });
    }
}
