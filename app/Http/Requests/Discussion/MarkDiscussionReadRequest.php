<?php

namespace App\Http\Requests\Discussion;

use App\Services\DiscussionService;
use Illuminate\Foundation\Http\FormRequest;
use Throwable;

class MarkDiscussionReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        try {
            return $this->user()->can('view', $this->subject());
        } catch (Throwable) {
            return false;
        }
    }

    public function rules(): array
    {
        return [];
    }

    public function subject(): object
    {
        return app(DiscussionService::class)->resolve((string) $this->route('subjectType'), (string) $this->route('subject'));
    }
}
