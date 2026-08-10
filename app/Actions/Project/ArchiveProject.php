<?php

namespace App\Actions\Project;

use App\Enums\ProjectStatus;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArchiveProject
{
    public function archive(Project $project, User $actor, ?string $ipAddress = null): void
    {
        DB::transaction(function () use ($project, $actor, $ipAddress) {
            $project->update([
                'status' => ProjectStatus::ARCHIVED,
                'archived_at' => now(),
                'version' => $project->version + 1,
            ]);
            ActivityLog::record($project->workspace, $project, 'project.archived', $actor, ipAddress: $ipAddress);
            $project->delete();
        });
    }

    public function restore(Project $project, User $actor, ?string $ipAddress = null): Project
    {
        return DB::transaction(function () use ($project, $actor, $ipAddress) {
            $project->restore();
            $project->update([
                'status' => ProjectStatus::ACTIVE,
                'archived_at' => null,
                'version' => $project->version + 1,
            ]);
            ActivityLog::record($project->workspace, $project, 'project.restored', $actor, ipAddress: $ipAddress);

            return $project;
        });
    }
}
