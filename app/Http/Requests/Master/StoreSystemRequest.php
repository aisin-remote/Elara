<?php

namespace App\Http\Requests\Master;

use App\Models\Workspace;
use App\Services\OrganizationDirectory;
use Illuminate\Validation\Rule;

class StoreSystemRequest extends MasterDataRequest
{
    public function rules(): array
    {
        $workspace = $this->targetWorkspace();

        // Editing a system no longer touches its PICs: it has one per department now, added and
        // removed on their own. Leaving the old single-PIC field in would quietly demote every
        // department but the one the form happened to carry.
        if ($this->route('system')) {
            return $this->detailRules($workspace);
        }

        $known = app(OrganizationDirectory::class)->departments()->pluck('id')->all();

        // With several PICs each has to say which department it answers for, otherwise there is
        // no way to tell them apart. A single PIC may still go without one: that is a system
        // serving one department, and it is also the only shape available while the directory
        // is down.
        $several = count($this->input('pics', [])) > 1;

        return [
            ...$this->detailRules($workspace),
            'pics' => ['required', 'array', 'min:1', 'max:5'],
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
                $several ? 'required' : 'nullable',
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
            'description' => ['nullable', 'string', 'max:2000'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
