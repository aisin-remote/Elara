<?php

namespace App\Http\Middleware;

use App\Services\OrganizationDirectory;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SyncOrganizationRole
{
    public function __construct(private readonly OrganizationDirectory $organization) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ($user->isOrganizationManaged() || ! $request->session()->has('organization_role_synced'))) {
            $this->organization->syncMembershipRoles($user);

            if (! $user->exists) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login')->withErrors([
                    'email' => 'Akun perusahaan Anda sudah tidak aktif.',
                ]);
            }

            $request->session()->put('organization_role_synced', true);
        }

        return $next($request);
    }
}
