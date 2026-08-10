<?php

namespace App\Http\Requests\Master;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreSupportArticleRequest extends MasterDataRequest
{
    public function rules(): array
    {
        $article = $this->route('article');

        return [
            'title' => ['required', 'string', 'max:180'],
            'category' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string'],
            'slug' => [
                'nullable', 'string', 'max:180', 'regex:/^[a-z0-9-]+$/',
                Rule::unique('support_articles', 'slug')->ignore($article?->id),
            ],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // A blank slug is derived rather than rejected: the author is writing content,
        // not URLs.
        if (! $this->filled('slug') && $this->filled('title')) {
            $this->merge(['slug' => Str::slug($this->string('title')->toString())]);
        }
    }
}
