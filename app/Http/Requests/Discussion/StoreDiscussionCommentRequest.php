<?php

namespace App\Http\Requests\Discussion;

use App\Models\DiscussionComment;
use App\Services\DiscussionService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Throwable;

class StoreDiscussionCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        try {
            return $this->user()->can('create', [DiscussionComment::class, $this->subject()]);
        } catch (Throwable) {
            return false;
        }
    }

    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'parent_public_id' => ['nullable', 'string', 'size:26'],
            'mentioned_user_public_ids' => ['nullable', 'array', 'max:20'],
            'mentioned_user_public_ids.*' => ['string', 'size:26', 'distinct'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'max:'.config('orbitra.max_file_upload_kb'), 'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt,zip'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $subject = $this->subject();
            if ($parent = $this->input('parent_public_id')) {
                $valid = $subject->discussionComments()->where('public_id', $parent)->exists();
                if (! $valid) {
                    $validator->errors()->add('parent_public_id', 'Choose a reply from this discussion.');
                }
            }

            $allowed = app(DiscussionService::class)->mentionableUsers($subject)->pluck('public_id')->all();
            foreach ($this->input('mentioned_user_public_ids', []) as $publicId) {
                if (! in_array($publicId, $allowed, true)) {
                    $validator->errors()->add('mentioned_user_public_ids', 'One mentioned person cannot access this discussion.');
                }
            }
        });
    }

    public function subject(): object
    {
        return app(DiscussionService::class)->resolve((string) $this->route('subjectType'), (string) $this->route('subject'));
    }
}
