<?php

namespace App\Http\Requests\Support;

use App\Models\SupportTicket;
use App\Models\Workspace;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        $workspace = $this->route('workspace');

        return $workspace instanceof Workspace && $this->user()->can('create', [SupportTicket::class, $workspace]);
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:180'],
            'body' => ['required', 'string', 'min:20', 'max:5000'],
        ];
    }
}
