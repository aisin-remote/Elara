<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\OrganizationDirectory;
use Illuminate\Console\Command;

class SyncOrganizationWorkspaces extends Command
{
    protected $signature = 'organization:sync-workspaces';

    protected $description = 'Move organization-managed users into their department workspace';

    public function handle(OrganizationDirectory $directory): int
    {
        $users = User::where('auth_source', 'organization')->get()
            ->sortBy(fn (User $user) => strcasecmp(
                (string) ($directory->profile($user)['department_code'] ?? ''),
                (string) config('organization.it_department_code'),
            ) === 0 ? 0 : 1);
        $synced = 0;

        foreach ($users as $user) {
            $synced += $directory->syncMembershipRoles($user) ? 1 : 0;
        }

        $this->info("Synced {$synced} organization users into department workspaces.");

        return self::SUCCESS;
    }
}
