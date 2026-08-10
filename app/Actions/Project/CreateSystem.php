<?php

namespace App\Actions\Project;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;

class CreateSystem
{
    /**
     * A system is a long-lived project, so it is created through the same machinery: its own
     * statuses, its own members, its own board. The PIC is its first project manager.
     */
    public function handle(Workspace $workspace, User $actor, array $data): Project
    {
        return DB::transaction(function () use ($workspace, $actor, $data): Project {
            $system = $workspace->projects()->create([
                'owner_id' => $actor->id,
                'name' => $data['name'],
                'type' => ProjectType::SYSTEM,
                'description' => $data['description'] ?? null,
                'color' => $data['color'],
                'status' => ProjectStatus::ACTIVE,
            ]);

            TaskStatus::createDefaultsFor($system);

            $system->memberships()->create([
                'user_id' => $data['pic_id'],
                'role' => ProjectMemberRole::MANAGER,
            ]);

            if ($data['pic_id'] !== $actor->id) {
                $system->memberships()->create([
                    'user_id' => $actor->id,
                    'role' => ProjectMemberRole::MANAGER,
                ]);
            }

            ActivityLog::record($workspace, $system, 'system.created', $actor, [
                'pic_id' => $data['pic_id'],
            ]);

            return $system;
        });
    }
}
