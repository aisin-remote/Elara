<?php

namespace App\Actions\Project;

use App\Enums\ProjectMemberRole;
use App\Models\ActivityLog;
use App\Models\Project;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanEntitlementService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function __construct(private readonly PlanEntitlementService $entitlements) {}

    public function handle(Workspace $workspace, User $owner, array $data, ?string $ipAddress = null): Project
    {
        $this->entitlements->assertCanCreateProject($workspace);

        return DB::transaction(function () use ($workspace, $owner, $data, $ipAddress) {
            $project = $workspace->projects()->create([
                ...Arr::except($data, 'member_public_ids'),
                'owner_id' => $owner->id,
            ]);

            $project->memberships()->create(['user_id' => $owner->id, 'role' => ProjectMemberRole::MANAGER]);
            TaskStatus::createDefaultsFor($project);

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
