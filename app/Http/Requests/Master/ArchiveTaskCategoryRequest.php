<?php

namespace App\Http\Requests\Master;

use Illuminate\Validation\Rule;

class ArchiveTaskCategoryRequest extends MasterDataRequest
{
    public function rules(): array
    {
        $category = $this->route('category');

        return [
            // Required only when tasks still point at this category: the admin must say
            // where they go, or explicitly clear them.
            'replacement_public_id' => [
                'nullable', 'string',
                Rule::exists('task_categories', 'public_id')
                    ->where('workspace_id', $category->workspace_id)
                    ->whereNull('archived_at')
                    ->whereNot('id', $category->id),
            ],
            'clear_tasks' => ['nullable', 'boolean'],
        ];
    }
}
