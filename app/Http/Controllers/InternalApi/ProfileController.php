<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Settings\UpdateProfileRequest;
use App\Http\Requests\Settings\UpdateThemeRequest;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse|RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();
        $emailChanged = $data['email'] !== $user->email;

        if ($request->boolean('remove_avatar') && $user->avatar_path) {
            Storage::disk(config('filesystems.default'))->delete($user->avatar_path);
            $user->avatar_path = null;
        }

        if ($request->hasFile('avatar')) {
            if ($user->avatar_path) {
                Storage::disk(config('filesystems.default'))->delete($user->avatar_path);
            }
            $avatar = $request->file('avatar');
            $user->avatar_path = $avatar->storeAs('avatars/'.$user->public_id, Str::uuid().'.'.$avatar->extension());
        }

        $user->fill(Arr::except($data, ['avatar', 'remove_avatar', 'current_password']));
        if ($emailChanged) {
            $user->email_verified_at = null;
        }
        $user->save();

        if ($emailChanged && config('orbitra.email_verification')) {
            $user->sendEmailVerificationNotification();
        }

        SecurityEvent::record($user, 'profile.updated', $request->ip(), $request->userAgent(), ['email_changed' => $emailChanged]);

        return $this->success($request, ['public_id' => $user->public_id], 'Profile updated.', url()->previous());
    }

    public function theme(UpdateThemeRequest $request): JsonResponse|RedirectResponse
    {
        $request->user()->update(['theme' => $request->string('theme')->toString()]);

        return $this->success($request, ['theme' => $request->string('theme')->toString()], 'Theme updated.', url()->previous());
    }
}
