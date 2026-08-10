<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Settings\SecurityPasswordRequest;
use App\Models\SecurityEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class SessionController extends Controller
{
    public function destroy(SecurityPasswordRequest $request, string $session): JsonResponse|RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 409);
        abort_if(hash_equals($request->session()->getId(), $session), 422, 'Use sign out to end the current session.');

        $deleted = DB::table(config('session.table', 'sessions'))
            ->where('id', $session)
            ->where('user_id', $request->user()->id)
            ->delete();
        abort_unless($deleted, 404);

        SecurityEvent::record($request->user(), 'session.revoked', $request->ip(), $request->userAgent());

        return $this->success($request, null, 'Session signed out.', url()->previous());
    }

    public function destroyOthers(SecurityPasswordRequest $request): JsonResponse|RedirectResponse
    {
        abort_unless(config('session.driver') === 'database', 409);
        DB::table(config('session.table', 'sessions'))
            ->where('user_id', $request->user()->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        SecurityEvent::record($request->user(), 'sessions.others_revoked', $request->ip(), $request->userAgent());

        return $this->success($request, null, 'Other sessions signed out.', url()->previous());
    }
}
