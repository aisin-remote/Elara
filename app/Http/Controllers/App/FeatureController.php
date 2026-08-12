<?php

namespace App\Http\Controllers\App;

use App\Enums\TaskStatusCategory;
use App\Http\Controllers\Controller;
use App\Models\Feature;
use App\Models\Project;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureController extends Controller
{
    public function create(Workspace $workspace): View
    {
        $this->authorize('create', [Project::class, $workspace]);

        return view('app.features.create', [
            'workspace' => $workspace,
            'systems' => $workspace->projects()
                ->systems()
                ->whereNull('archived_at')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /** One card per system the user can see. */
    public function index(Request $request, Workspace $workspace, OrganizationDirectory $organization): View
    {
        $this->authorize('viewAny', [Project::class, $workspace]);

        $search = trim($request->string('search')->toString());
        $pic = $request->string('pic')->toString();
        $visibleUserIds = $organization->taskMembers($request->user(), $workspace)->pluck('id');

        $systems = $workspace->projects()
            ->systems()
            ->visibleTo($request->user())
            ->whereNull('archived_at')
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $match) => $match
                ->where('name', 'like', '%'.$search.'%')
                ->orWhere('description', 'like', '%'.$search.'%')))
            ->with('members:id,public_id,first_name,last_name,avatar_path')
            ->withCount([
                'features as active_features_count' => fn (Builder $query) => $query->whereNull('archived_at'),
                'tasks as open_tasks_count' => fn (Builder $query) => $query
                    ->whereNull('archived_at')
                    ->whereHas('assignees', fn (Builder $assignees) => $assignees->whereIn('users.id', $visibleUserIds))
                    ->whereHas('status', fn (Builder $status) => $status->whereNotIn('category', [
                        TaskStatusCategory::COMPLETED->value,
                        TaskStatusCategory::CANCELLED->value,
                    ])),
            ])
            ->orderBy('name')
            ->get();

        // Resolved once per system and reused, so the filter, the card, and the picker all
        // agree on who the PIC is rather than each asking again.
        $withPic = $systems->map(fn (Project $system) => [
            'model' => $system,
            'pic' => $system->pic(),
            'progress' => $system->taskProgress($request->user()),
        ]);

        return view('app.features.index', [
            'workspace' => $workspace,
            'search' => $search,
            'pic' => $pic,
            // Only PICs that actually own a system: a picker listing people who own nothing
            // returns an empty page and reads as a broken filter.
            'pics' => $withPic->pluck('pic')->filter()->unique('id')->sortBy('first_name')->values(),
            'systems' => $pic === ''
                ? $withPic
                : $withPic->filter(fn (array $entry) => $entry['pic']?->public_id === $pic)->values(),
        ]);
    }

    /** One system: its features, and the tasks inside each. */
    public function show(Request $request, Workspace $workspace, Project $system): View
    {
        abort_unless($system->workspace_id === $workspace->id && $system->isSystem(), 404);
        $this->authorize('view', $system);

        $features = $system->features()
            ->with([
                'tasks' => fn ($query) => $query->visibleTo($request->user())->whereNull('archived_at')->with(['status', 'assignees'])->orderBy('position'),
                'breakdowns' => fn ($query) => $query->with('acceptedBy')->latest('id'),
            ])
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get();

        return view('app.features.show', [
            'workspace' => $workspace,
            'system' => $system,
            'features' => $features,
            'progress' => $system->taskProgress($request->user()),
            // Maintenance work that belongs to no feature still has to be visible somewhere.
            'looseTasks' => $system->tasks()
                ->visibleTo($request->user())
                ->whereNull('feature_id')
                ->whereNull('archived_at')
                ->with(['status', 'assignees'])
                ->orderBy('position')
                ->get(),
        ]);
    }

    /** One feature: its own progress, people, tasks, and any draft still awaiting review. */
    public function detail(Request $request, Workspace $workspace, Project $system, Feature $feature): View
    {
        abort_unless(
            $system->workspace_id === $workspace->id
                && $system->isSystem()
                && $feature->workspace_id === $workspace->id
                && $feature->project_id === $system->id,
            404,
        );
        $this->authorize('view', $system);

        $feature->load([
            'tasks' => fn ($query) => $query
                ->visibleTo($request->user())
                ->whereNull('archived_at')
                ->with(['status', 'assignees'])
                ->orderBy('position'),
        ]);

        $progress = $feature->progress($request->user());

        return view('app.features.detail', [
            'workspace' => $workspace,
            'system' => $system,
            'feature' => $feature,
            'tasks' => $feature->tasks,
            'progress' => $progress,
            'overdueTaskCount' => $feature->tasks->filter(fn ($task) => ! $task->completed_at && $task->due_at?->isPast())->count(),
            'assignees' => $feature->tasks->flatMap(fn ($task) => $task->assignees)->unique('id')->values(),
            'breakdown' => $feature->breakdowns()->with('acceptedBy')->latest('id')->first(),
        ]);
    }
}
