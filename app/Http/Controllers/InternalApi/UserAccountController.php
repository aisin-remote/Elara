<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\User\DeleteUserAccount;
use App\Models\User;
use App\Models\WorkspaceMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserAccountController extends Controller
{
    /**
     * Permanently deletes a person's account. The membership in the route is what the actor is
     * looking at when they press the button; the Action re-checks every other workspace the
     * person belongs to before anything is removed.
     */
    public function destroy(Request $request, WorkspaceMember $member, DeleteUserAccount $delete): JsonResponse|RedirectResponse
    {
        $this->authorize('deleteAccount', $member);

        $user = $member->user;
        abort_unless($user instanceof User, 404);

        $name = $user->name;
        $delete->handle($user, $request->user());

        return $this->success(
            $request,
            [],
            $name.'’s account was deleted. The work they raised is kept under “Deleted user”.',
            route('app.workspaces.team', $member->workspace),
        );
    }
}
