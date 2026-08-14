<?php

namespace App\Http\Controllers\InternalApi;

use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProjectTemplateController extends Controller
{
    public function store(Request $request, Project $project): RedirectResponse
    {
        $this->authorize('manageWorkflow', [Task::class, $project]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('project_templates')->where('workspace_id', $project->workspace_id)],
        ]);

        $project->load(['taskStatuses' => fn ($query) => $query->active(), 'taskProperties' => fn ($query) => $query->active()]);
        ProjectTemplate::create([
            'workspace_id' => $project->workspace_id,
            'created_by' => $request->user()->id,
            'name' => $data['name'],
            'task_fields_json' => $project->task_fields_json,
            'statuses_json' => $project->taskStatuses->map(fn ($status) => [
                'name' => $status->name, 'color' => $status->color,
                'category' => $status->category->value, 'position' => $status->position,
            ])->all(),
            'properties_json' => $project->taskProperties->map(fn ($property) => [
                'name' => $property->name, 'type' => $property->type->value,
                'options_json' => $property->options_json, 'position' => $property->position,
            ])->all(),
        ]);

        return back()->with('success', 'Project workflow template saved.');
    }

    public function destroy(Request $request, ProjectTemplate $projectTemplate): RedirectResponse
    {
        $this->authorize('update', $projectTemplate->workspace);
        $projectTemplate->delete();

        return back()->with('success', 'Project template deleted.');
    }
}
