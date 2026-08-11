<?php

namespace App\Actions\Feature;

use App\Enums\ProjectMemberRole;
use App\Models\ActivityLog;
use App\Models\Feature;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateFeature
{
    public function handle(Project $system, User $actor, array $data, ?string $ipAddress = null): Feature
    {
        return DB::transaction(function () use ($system, $actor, $data, $ipAddress): Feature {
            $feature = Feature::create([
                'workspace_id' => $system->workspace_id,
                'project_id' => $system->id,
                ...Arr::only($data, ['name', 'description', 'starts_at', 'due_at']),
            ]);

            $system->memberships()->firstOrCreate(
                ['user_id' => $actor->id],
                ['role' => ProjectMemberRole::MEMBER],
            );

            ActivityLog::record($system->workspace, $feature, 'feature.created', $actor, ipAddress: $ipAddress);

            return $feature;
        });
    }
}
