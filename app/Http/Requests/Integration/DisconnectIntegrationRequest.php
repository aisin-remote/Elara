<?php

namespace App\Http\Requests\Integration;

use App\Models\IntegrationConnection;
use Illuminate\Foundation\Http\FormRequest;

class DisconnectIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $connection = $this->route('connection');

        return $connection instanceof IntegrationConnection && $this->user()->can('delete', $connection);
    }

    public function rules(): array
    {
        return [];
    }
}
