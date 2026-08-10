<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;

class UpdateTaskCategoryRequest extends MasterDataRequest
{
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:80',
                Rule::unique('task_categories', 'name')
                    ->where('workspace_id', $this->route('category')->workspace_id)
                    ->ignore($this->route('category')->id),
            ],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
