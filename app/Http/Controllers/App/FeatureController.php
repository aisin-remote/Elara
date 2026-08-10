<?php

namespace App\Http\Controllers\App;

use App\Enums\TaskStatusCategory;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FeatureController extends Controller
{
    /** One card per system the user can see. */
    public function index(Request $request, Workspace $workspace): View
    {
        $this->authorize('viewAny', [Project::class, $workspace]);

        $search = trim($request->string('search')->toString());
        $pic = $request->string('pic')->toString();

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
            'progress' => $system->taskProgress(),
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
    public function show(Workspace $workspace, Project $system): View
    {
        abort_unless($system->workspace_id === $workspace->id && $system->isSystem(), 404);
        $this->authorize('view', $system);

        $features = $system->features()
            ->with(['tasks' => fn ($query) => $query->whereNull('archived_at')->with(['status', 'assignees'])->orderBy('position')])
            ->orderByRaw('archived_at is not null')
            ->orderBy('name')
            ->get();

        return view('app.features.show', [
            'workspace' => $workspace,
            'system' => $system,
            'features' => $features,
            'progress' => $system->taskProgress(),
            // Maintenance work that belongs to no feature still has to be visible somewhere.
            'looseTasks' => $system->tasks()
                ->whereNull('feature_id')
                ->whereNull('archived_at')
                ->with(['status', 'assignees'])
                ->orderBy('position')
                ->get(),
        ]);
    }
}
