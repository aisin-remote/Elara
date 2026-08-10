<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // No 'system': the control is a two-way toggle now, so accepting a value nothing can
        // send would leave a state the UI cannot show. Accounts still holding 'system' from
        // before are read fine — the front end resolves them on first load.
        return ['theme' => ['required', Rule::in(['light', 'dark'])]];
    }
}
