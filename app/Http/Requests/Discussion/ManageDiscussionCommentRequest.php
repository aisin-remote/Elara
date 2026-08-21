<?php

namespace App\Http\Requests\Discussion;

use App\Models\DiscussionComment;
use Illuminate\Foundation\Http\FormRequest;

class ManageDiscussionCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $comment = $this->route('discussionComment');
        if (! $comment instanceof DiscussionComment) {
            return false;
        }

        return $this->isMethod('DELETE') ? $this->user()->can('delete', $comment) : $this->user()->can('pin', $comment);
    }

    public function rules(): array
    {
        return ['pinned' => ['sometimes', 'boolean']];
    }
}
