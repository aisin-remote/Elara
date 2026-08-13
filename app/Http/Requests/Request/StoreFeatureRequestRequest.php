<?php

namespace App\Http\Requests\Request;

use App\Enums\ProjectType;
use App\Enums\RequestUrgency;
use App\Models\FeatureRequest;
use App\Models\Workspace;
use App\Services\DepartmentWorkspaceService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFeatureRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', [FeatureRequest::class, $this->route('workspace')]);
    }

    public function rules(): array
    {
        $workspace = $this->route('workspace');

        if (config('organization.jit_auth')) {
            $workspace = app(DepartmentWorkspaceService::class)->deliveryWorkspace();
        }

        return [
            // Only an active system, and only one that has a PIC — nobody can be assigned
            // work on a system with no owner (PRD-02).
            'system_public_id' => ['required', 'string', Rule::in($this->eligibleSystemIds($workspace))],
            'title' => ['required', 'string', 'max:200'],
            'problem' => ['required', 'string', 'min:20', 'max:4000'],
            'desired_outcome' => ['required', 'string', 'min:20', 'max:4000'],
            'benefit' => ['required', 'string', 'min:20', 'max:4000'],
            'urgency' => ['required', Rule::enum(RequestUrgency::class)],
        ];
    }

    public function messages(): array
    {
        return [
            'problem.min' => 'Describe the current condition in a sentence or two — a thin request produces thin work.',
            'desired_outcome.min' => 'Describe the target condition — what "done" looks like from your side.',
            'benefit.min' => 'Describe the benefit this change would bring.',
        ];
    }

    /** @return array<int, string> */
    private function eligibleSystemIds(?Workspace $workspace): array
    {
        return $workspace
            ? $workspace->projects()
                ->where('type', ProjectType::SYSTEM->value)
                ->whereNull('archived_at')
                ->get()
                ->filter(fn ($system) => $system->pic() !== null)
                ->pluck('public_id')
                ->all()
            : [];
    }
}
