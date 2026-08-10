<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Denies the IT delivery desk to requesters. Deliberately a deny-list on the whole
 * /app group rather than an allow-list per route, so a delivery route added later is
 * closed to requesters by default instead of open until somebody remembers.
 */
class EnsureDeliveryDeskAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $memberships = $request->user()?->workspaceMemberships()->active()->get();

        // A member of nothing is on their way to creating a workspace, not a requester.
        $blocked = $memberships !== null
            && $memberships->isNotEmpty()
            && $memberships->every(fn ($membership) => ! $membership->role->canAccessDeliveryDesk());

        abort_if($blocked, 403, 'This account uses the request desk.');

        return $next($request);
    }
}
