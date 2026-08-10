<?php

namespace App\Http\Requests\Messaging;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('send', $this->route('conversation'));
    }

    public function rules(): array
    {
        return [
            'body' => ['nullable', 'required_without:attachments', 'string', 'max:10000'],
            'attachments' => ['nullable', 'required_without:body', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:'.config('orbitra.max_file_upload_kb', 10240), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (blank(trim((string) $this->input('body'))) && ! $this->hasFile('attachments')) {
                $validator->errors()->add('body', 'Write a message or attach a file.');
            }
        });
    }
}
