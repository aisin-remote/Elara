<?php

namespace App\Http\Requests\Task;

use App\Services\RequestTaskAccess;
use Illuminate\Foundation\Http\FormRequest;

class StoreTaskAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $task = $this->route('task');

        return $this->user()->can('update', $task)
            || ($this->boolean('share_with_requester')
                && app(RequestTaskAccess::class)->ownedRequest($this->user(), $task) !== null);
    }

    public function rules(): array
    {
        return [
            'attachment' => ['required', 'file', 'max:'.config('orbitra.max_file_upload_kb'), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
            'share_with_requester' => ['nullable', 'boolean'],
        ];
    }
}
