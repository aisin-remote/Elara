<?php

namespace App\Actions\Project;

use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Support\Facades\DB;

class UpdateProject
{
    public function __construct(private readonly NotificationPreferenceService $notifications) {}

    public function handle(Project $project, User $actor, array $data, int $version, ?string $ipAddress = null): ?Project
    {
        $project = DB::transaction(function () use ($project, $actor, $data, $version, $ipAddress) {
            $updated = Project::query()
                ->whereKey($project->id)
                ->where('version', $version)
                ->update([...$data, 'version' => DB::raw('version + 1')]);

            if (! $updated) {
                return null;
            }

            $project = $project->fresh();
            ActivityLog::record($project->workspace, $project, 'project.updated', $actor, ipAddress: $ipAddress);

            return $project;
        });

        if ($project) {
            $project->loadMissing(['members', 'workspace']);
            $project->members->where('id', '!=', $actor->id)->each(fn (User $recipient) => $this->notifications->notify(
                $recipient,
                $project->workspace,
                'project_updated',
                'Project updated',
                $actor->name.' updated “'.$project->name.'”.',
                route('app.projects.show', $project),
                ['project_public_id' => $project->public_id],
            ));
        }

        return $project;
    }
}
