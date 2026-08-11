<?php

namespace App\Http\Requests\Master;

use App\Services\OrganizationDirectory;
use Illuminate\Validation\Rule;

/**
 * Naming the PIC for one department of a system. The department is the key: assigning again
 * replaces whoever held it, rather than adding a second person nobody chose between.
 */
class AssignSystemPicRequest extends MasterDataRequest
{
    public function rules(): array
    {
        // Checked against the directory only when the directory answered. An empty list means
        // someone else's database is down, and refusing every id then would make this screen
        // unusable for a reason that has nothing to do with the person using it.
        $known = app(OrganizationDirectory::class)->departments()->pluck('id')->all();

        return [
            // Required here, unlike on the system itself: a PIC without a department is exactly
            // the arrangement this screen exists to replace.
            'organization_department_id' => array_values(array_filter([
                'required', 'integer', 'min:1',
                $known === [] ? null : Rule::in($known),
            ])),
            'pic_public_id' => [
                'required', 'string',
                Rule::exists('users', 'public_id')->where(
                    fn ($query) => $query->whereIn('id', $this->eligiblePicIds($this->targetWorkspace()))
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_department_id.required' => 'Choose which department this PIC answers for.',
            'organization_department_id.in' => 'Choose a department from the organisation directory.',
            'pic_public_id.exists' => 'Choose an active member who can work on this system.',
        ];
    }
}
