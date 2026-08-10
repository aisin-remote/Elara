<?php

namespace App\Http\Requests\Master;

use App\Models\CapacityException;
use App\Models\Project;
use App\Models\TaskCategory;
use App\Models\TaskStatusTemplate;
use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Shared authorisation for every master mutation. The workspace comes from the route, from
 * the record being edited, or — for deployment-wide records like help articles — from any
 * workspace this user administers.
 */
abstract class MasterDataRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->targetWorkspace();

        // No resolvable workspace means no administered workspace, which is a denial —
        // never a fall-through to Gate's null handling.
        return $workspace !== null && $this->user()->can('manageMasterData', $workspace);
    }

    protected function targetWorkspace(): ?Workspace
    {
        $route = $this->route('workspace');

        if ($route instanceof Workspace) {
            return $route;
        }

        $record = $this->route('category') ?? $this->route('template') ?? $this->route('system')
            ?? $this->route('exception') ?? $this->route('holiday');

        if ($record instanceof TaskCategory || $record instanceof TaskStatusTemplate) {
            return $record->workspace;
        }

        if ($record instanceof Project || $record instanceof CapacityException || $record instanceof WorkspaceHoliday) {
            return $record->workspace;
        }

        return $this->administeredWorkspace();
    }

    private function administeredWorkspace(): ?Workspace
    {
        return $this->user()?->workspaceMemberships()
            ->active()
            ->with('workspace')
            ->get()
            ->map(fn ($membership) => $membership->workspace)
            ->first(fn (?Workspace $workspace) => $workspace && $this->user()->can('manageMasterData', $workspace));
    }
}
