<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The mirror of EnsureDeliveryDeskAccess: denies the requester desk to the delivery team.
 * A deny-list on the whole /desk group for the same reason as the other side — a desk route
 * added later is closed by default rather than open until somebody remembers.
 *
 * Blocked only when *every* membership is a delivery one, so a person who is a requester in
 * one workspace keeps their desk even after being invited to an IT team elsewhere.
 */
class EnsureRequestDeskAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $memberships = $request->user()?->workspaceMemberships()->active()->get();

        // A member of nothing is on their way to creating a workspace, not delivery staff.
        $blocked = $memberships !== null
            && $memberships->isNotEmpty()
            && $memberships->every(fn ($membership) => ! $membership->role->canUseRequestDesk());

        abort_if($blocked, 403, 'This account uses the delivery desk.');

        return $next($request);
    }
}
