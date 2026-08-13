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

        // Editing posts the whole PIC list, the same rows the create form uses, and the ones it
        // leaves out are the ones to drop. A system with no PIC yet must still be editable, so
        // here the list may be empty — on create it may not.
        $editing = (bool) $this->route('system');

        // With several PICs each has to say which department it answers for, otherwise there is
        // no way to tell them apart. A single PIC may still go without one: that is a system
        // serving one department, and it is also the only shape available while the directory
        // is down.
        $several = count($this->input('pics', [])) > 1;

        return [
            ...$this->detailRules($workspace),
            'pics' => $editing ? ['array', 'max:5'] : ['required', 'array', 'min:1', 'max:5'],
            // The PIC must be someone who can actually work on it: an active member who is
            // not a requester.
            'pics.*.pic_public_id' => [
                'required', 'string',
                Rule::exists('users', 'public_id')->where(
                    fn ($query) => $query->whereIn('id', $this->eligiblePicIds($workspace))
                ),
            ],
            // Checked against the directory only when the directory answered. An empty list
            // means someone else's database is down, and refusing every id then would make
            // this screen unusable for a reason that has nothing to do with the person using it.
            'pics.*.organization_department_id' => array_values(array_filter([
                // On edit the department is the key the sync removes by, so a row without one
                // would silently do nothing — unless the directory is down and none can be shown.
                $several || ($editing && $known !== []) ? 'required' : 'nullable',
                'integer', 'distinct',
                $known === [] ? null : Rule::in($known),
            ])),
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

        $pics = $this->input('pics');

        if (! is_array($pics)) {
            return;
        }

        $this->merge([
            'pics' => array_values(array_filter(
                $pics,
                fn ($row) => is_array($row) && filled($row['pic_public_id'] ?? null),
            )),
        ]);
    }

    public function messages(): array
    {
        return [
            'pics.required' => 'Name at least one PIC — a system with none cannot receive feature requests.',
            'pics.*.organization_department_id.required' => 'Say which department each PIC answers for.',
            'pics.*.organization_department_id.distinct' => 'Each department can have only one PIC on a system.',
            'pics.*.organization_department_id.in' => 'Choose a department from the organisation directory.',
            'pics.*.pic_public_id.exists' => 'Choose an active member who can work on this system.',
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
