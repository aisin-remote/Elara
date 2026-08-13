<?php

namespace App\Actions\Project;

use App\Enums\ProjectMemberRole;
use App\Enums\ProjectStatus;
use App\Enums\ProjectType;
use App\Enums\SystemPlant;
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
            $plant = $data['plant'] ?? SystemPlant::BODY;

            $system = $workspace->projects()->create([
                'owner_id' => $actor->id,
                'name' => $data['name'],
                'type' => ProjectType::SYSTEM,
                'plant' => $plant instanceof SystemPlant ? $plant : SystemPlant::from($plant),
                'description' => $data['description'] ?? null,
                'color' => $data['color'],
                'status' => ProjectStatus::ACTIVE,
            ]);

            TaskStatus::createDefaultsFor($system);

            // The department rides on the assignment rather than on the system: this is the PIC
            // for that department, and more can be named beside it later. The code travels with
            // the id so a screen can print a label without the directory being up.
            $system->memberships()->create([
                'user_id' => $data['pic_id'],
                'role' => ProjectMemberRole::MANAGER,
                'organization_department_id' => $data['organization_department_id'] ?? null,
                'organization_department_code' => $data['organization_department_code'] ?? null,
            ]);

            // The creator is a manager too, but for no department in particular: they can run
            // the system without being the person any department is routed to.
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
