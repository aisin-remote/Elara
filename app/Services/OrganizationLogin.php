<?php

namespace App\Services;

use App\Enums\WorkspaceRole;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class OrganizationLogin
{
    public function __construct(
        private readonly OrganizationDirectory $directory,
        private readonly DepartmentWorkspaceService $departmentWorkspaces,
    ) {}

    public function attempt(string $email, string $password): ?User
    {
        $profile = $this->directory->authenticate($email, $password);

        if (! $profile) {
            return null;
        }

        $existing = User::query()
            ->where('organization_user_id', $profile['organization_user_id'])
            ->orWhere('email', $profile['email'])
            ->first();

        if ($existing?->workspaceMemberships()->active()
            ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
            ->exists()) {
            return null;
        }

        try {
            $workspace = $this->departmentWorkspaces->workspaceFor($profile);
            $user = DB::transaction(function () use ($existing, $profile, $workspace): ?User {
                $byOrganizationId = User::where('organization_user_id', $profile['organization_user_id'])->lockForUpdate()->first();
                $byEmail = User::where('email', $profile['email'])->lockForUpdate()->first();

                if ($byOrganizationId && $byEmail && ! $byOrganizationId->is($byEmail)) {
                    return null;
                }

                $user = $byOrganizationId ?? $byEmail ?? $existing;
                [$firstName, $lastName] = $this->splitName($profile['name']);
                $attributes = [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'email' => $profile['email'],
                    'email_verified_at' => now(),
                    'auth_source' => 'organization',
                    'organization_user_id' => $profile['organization_user_id'],
                    'organization_synced_at' => now(),
                    'job_title' => $profile['rank_name'],
                    'company' => $profile['division_name'],
                ];

                if (! $user) {
                    $user = User::create([
                        ...$attributes,
                        'password' => Str::random(64),
                        'locale' => $workspace->locale,
                        'timezone' => $workspace->timezone,
                    ]);
                } else {
                    if (! $user->isOrganizationManaged()) {
                        $attributes['password'] = Str::random(64);
                        $attributes['remember_token'] = null;
                    }

                    $user->forceFill($attributes)->save();
                }

                return $user;
            });

            if ($user) {
                $this->departmentWorkspaces->syncMembership(
                    $user,
                    $profile,
                    $this->directory->workspaceRole($profile),
                );
            }
        }catch (QueryException|RuntimeException $e) {
            report($e);

            throw $e;
        }

        if (! $user) {
            return null;
        }

        Auth::login($user, false);

        return $user;
    }

    /** @return array{string, string} */
    private function splitName(?string $name): array
    {
        $parts = explode(' ', preg_replace('/\s+/', ' ', trim((string) $name)), 2);

        return [
            Str::limit($parts[0] ?: 'User', 80, ''),
            Str::limit($parts[1] ?? '', 80, ''),
        ];
    }
}
