<?php

namespace App\Http\Requests\Master;

use App\Enums\ProjectType;
use App\Enums\SystemPlant;
use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Illuminate\Validation\Rule;

class StoreSystemRequest extends MasterDataRequest
{
    public function rules(): array
    {
        $workspace = $this->targetWorkspace();
        $known = app(OrganizationDirectory::class)->departments()->pluck('id')->all();
        $editing = (bool) $this->route('system');

        return [
            ...$this->detailRules($workspace),
            // On edit this field is omitted when PostgreSQL is unavailable, preserving the
            // current assignments while still allowing system details to be corrected.
            'departments' => $editing
                ? ['sometimes', 'array', 'min:1', 'max:5']
                : ['required', 'array', 'min:1', 'max:5'],
            'departments.*' => [
                'required',
                'integer',
                'distinct',
                Rule::in($known),
                Rule::exists('department_pics', 'organization_department_id')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace?->id)),
            ],
        ];
    }

    /**
     * Rows the form left blank are dropped before validation. Every row is rendered up front so
     * the pickers can be server-rendered, which means the unused ones post empty strings — and
     * those would otherwise fail "required" on a row nobody filled in.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('color')) {
            $this->merge(['color' => strtolower($this->string('color'))]);
        }

        $departments = $this->input('departments');

        if (! is_array($departments)) {
            return;
        }

        $this->merge([
            'departments' => array_values(array_filter($departments, fn ($id) => filled($id))),
        ]);
    }

    public function messages(): array
    {
        return [
            'departments.required' => 'Choose at least one department served by this system.',
            'departments.*.distinct' => 'Each department may be selected only once.',
            'departments.*.in' => 'Choose a department from the live organisation directory.',
            'departments.*.exists' => 'Set the default PIC for this department under Master data → Departments first.',
            'color.unique' => 'That colour is already used by another system. Pick or randomise a free one.',
        ];
    }

    /** @return array<string, array<int, mixed>> */
    private function detailRules(?Workspace $workspace): array
    {
        return [
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('projects', 'name')
                    ->where('workspace_id', $workspace?->id)
                    ->ignore($this->route('system')?->id),
            ],
            'plant' => ['required', Rule::enum(SystemPlant::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => [
                'required',
                'regex:/^#[0-9A-Fa-f]{6}$/',
                Rule::unique('projects', 'color')
                    ->where('workspace_id', $workspace?->id)
                    ->where('type', ProjectType::SYSTEM->value)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('system')?->id),
            ],
        ];
    }
}
