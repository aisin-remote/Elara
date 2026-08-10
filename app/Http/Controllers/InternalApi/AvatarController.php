<?php

namespace App\Http\Controllers\InternalApi;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AvatarController extends Controller
{
    public function show(Request $request, User $user): StreamedResponse
    {
        $allowed = $request->user()->is($user) || $request->user()->workspaceMemberships()
            ->whereIn('workspace_id', $user->workspaceMemberships()->select('workspace_id'))
            ->active()
            ->exists();
        abort_unless($allowed && $user->avatar_path, 404);

        $disk = Storage::disk(config('filesystems.default'));
        abort_unless($disk->exists($user->avatar_path), 404);

        return $disk->response($user->avatar_path, 'avatar', ['Cache-Control' => 'private, max-age=3600']);
    }
}
