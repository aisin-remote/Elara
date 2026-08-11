<?php

namespace App\Http\Requests\Request;

class ResubmitProjectRequestRequest extends StoreProjectRequestRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('resubmit', $this->route('projectRequest'));
    }
}
