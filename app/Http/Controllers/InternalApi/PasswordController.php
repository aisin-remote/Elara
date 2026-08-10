<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Settings\UpdatePasswordRequest;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function update(UpdatePasswordRequest $request): JsonResponse|RedirectResponse
    {
        if ($request->user()->isOrganizationManaged()) {
            throw ValidationException::withMessages([
                'password' => 'Your password is managed by the company directory. Change it through the company account service.',
            ]);
        }

        $request->user()->update(['password' => $request->string('password')->toString()]);
        SecurityEvent::record($request->user(), 'password.changed', $request->ip(), $request->userAgent());

        return $this->success($request, null, 'Password changed.', url()->previous());
    }
}
