<?php

namespace App\Actions\Project;

use App\Enums\ProjectMemberRole;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function handle(Workspace $workspace, User $owner, array $data, ?string $ipAddress = null): Project
    {
        return DB::transaction(function () use ($workspace, $owner, $data, $ipAddress) {
            $template = filled($data['template_public_id'] ?? null)
                ? ProjectTemplate::where('workspace_id', $workspace->id)->where('public_id', $data['template_public_id'])->firstOrFail()
                : null;
            $project = $workspace->projects()->create([
                ...Arr::except($data, ['member_public_ids', 'generate_with_ai', 'template_public_id']),
                'owner_id' => $owner->id,
                'task_fields_json' => $template?->task_fields_json,
            ]);

            $project->memberships()->create(['user_id' => $owner->id, 'role' => ProjectMemberRole::MANAGER]);
            if ($template) {
                foreach ($template->statuses_json as $status) {
                    $project->taskStatuses()->create([...$status, 'is_system' => true]);
                }
                foreach ($template->properties_json as $property) {
                    $project->taskProperties()->create($property);
                }
            } else {
                TaskStatus::createDefaultsFor($project);
            }

            $memberIds = $workspace->memberships()
                ->active()
                ->whereHas('user', fn ($query) => $query->whereIn('public_id', $data['member_public_ids'] ?? []))
                ->pluck('user_id');

            foreach ($memberIds as $memberId) {
                $project->memberships()->firstOrCreate(['user_id' => $memberId], ['role' => ProjectMemberRole::MEMBER]);
            }

            ActivityLog::record($workspace, $project, 'project.created', $owner, ipAddress: $ipAddress);

            return $project;
        });
    }
}
