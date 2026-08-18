<?php

namespace App\Services;

use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Collection;

class RequestTaskAccess
{
    public function visibleRequest(User $user, Task $task): FeatureRequest|ProjectRequest|null
    {
        return $this->linkedRequests($task)
            ->first(fn (FeatureRequest|ProjectRequest $request) => $user->can('view', $request));
    }

    public function ownedRequest(User $user, Task $task): FeatureRequest|ProjectRequest|null
    {
        return $this->linkedRequests($task)
            ->first(fn (FeatureRequest|ProjectRequest $request) => $request->requester_id === $user->id
                && $user->can('view', $request));
    }

    public function detailUrl(FeatureRequest|ProjectRequest $request): string
    {
        return $request instanceof FeatureRequest
            ? route('desk.requests.show', $request)
            : route('desk.project-requests.show', $request);
    }

    private function linkedRequests(Task $task): Collection
    {
        $featureRequests = $task->feature_id
            ? FeatureRequest::query()->where('feature_id', $task->feature_id)->latest('id')->get()
            : collect();
        $projectRequests = ProjectRequest::query()
            ->where('project_id', $task->project_id)
            ->latest('id')
            ->get();

        return $featureRequests->concat($projectRequests);
    }
}
