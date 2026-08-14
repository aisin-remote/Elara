<?php

namespace App\Http\Controllers\InternalApi;

use App\Models\Project;
use App\Models\Task;
use App\Models\TaskView;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskViewController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('viewAny', [Task::class, $project]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('task_views')->where(fn ($query) => $query
                ->where('project_id', $project->id)->where('user_id', $request->user()->id))],
            'search' => ['nullable', 'string', 'max:160'],
            'group_by' => ['nullable', 'string', 'max:80'],
            'priority' => ['nullable', 'string', 'max:20'],
            'blocked' => ['nullable', 'boolean'],
            'sort' => ['nullable', Rule::in(['position', 'title', 'due_at', 'updated_at'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'fields' => ['nullable', 'array'],
            'fields.*' => ['string', 'max:40'],
        ]);

        $view = $project->taskViews()->create([
            'workspace_id' => $project->workspace_id,
            'user_id' => $request->user()->id,
            'name' => $data['name'],
            'parameters_json' => collect($data)->except('name')->filter(fn ($value) => $value !== null && $value !== '')->all(),
        ]);

        return back()->with('success', "View {$view->name} saved.");
    }

    public function destroy(Request $request, TaskView $taskView): RedirectResponse
    {
        abort_unless($taskView->user_id === $request->user()->id, 403);
        $taskView->delete();

        return redirect($taskView->project->taskListUrl())->with('success', 'Saved view deleted.');
    }
}
