<?php

namespace App\Http\Requests\Master;

use App\Services\OrganizationDirectory;
use Illuminate\Validation\Rule;

class SaveDepartmentPicRequest extends MasterDataRequest
{
    public function rules(): array
    {
        $knownDepartments = app(OrganizationDirectory::class)->departments()->pluck('id')->all();

        return [
            'organization_department_id' => [
                'required',
                'integer',
                Rule::in($knownDepartments),
            ],
            'pic_public_id' => [
                'required',
                'string',
                Rule::exists('users', 'public_id')->where(
                    fn ($query) => $query->whereIn('id', $this->eligiblePicIds($this->targetWorkspace()))
                ),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'organization_department_id.in' => 'Choose a department from the live organisation directory.',
            'pic_public_id.exists' => 'Choose an active IT member who can own delivery work.',
        ];
    }
}
