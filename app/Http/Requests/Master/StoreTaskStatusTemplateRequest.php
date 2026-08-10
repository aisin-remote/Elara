<?php

namespace App\Http\Requests\Master;

use App\Enums\TaskStatusCategory;
use Illuminate\Validation\Rule;

class StoreTaskStatusTemplateRequest extends MasterDataRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'category' => ['required', Rule::enum(TaskStatusCategory::class)],
        ];
    }
}
