<?php

namespace App\Http\Requests\Request;

use Illuminate\Foundation\Http\FormRequest;

class StoreRequestAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $subject = $this->route('featureRequest') ?? $this->route('projectRequest');

        // Attaching is part of making the case, so it follows the same rule as editing the
        // request: your own, and only while it is still open.
        return $subject !== null
            && $subject->requester_id === $this->user()->id
            && $subject->status->isOpen();
    }

    public function rules(): array
    {
        return [
            // The same allow-list the board uses. A request is not a reason to accept file
            // types the rest of the product refuses.
            'file' => ['required', 'file', 'max:'.config('orbitra.max_file_upload_kb'), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ];
    }

    public function messages(): array
    {
        return [
            'file.mimes' => 'Attach an image, a document, a spreadsheet, or a zip.',
            'file.max' => 'That file is larger than this workspace allows.',
        ];
    }
}
