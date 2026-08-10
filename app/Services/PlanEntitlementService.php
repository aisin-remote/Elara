<?php

namespace App\Services;

use App\Models\Workspace;

class PlanEntitlementService
{
    public function plan(Workspace $workspace): string
    {
        return 'unlimited';
    }

    /** @return array<string, mixed> */
    public function details(Workspace $workspace): array
    {
        return ['key' => 'unlimited', ...config('plans.plans.unlimited')];
    }

    public function assertCanInviteMember(Workspace $workspace): void {}

    public function assertCanCreateProject(Workspace $workspace): void {}

    public function assertCanStoreBytes(Workspace $workspace, int $bytes): void {}

    public function assertCanConnectIntegration(Workspace $workspace): void {}

    public function assertCanExport(Workspace $workspace): void {}

    public function limit(Workspace $workspace, string $feature): mixed
    {
        return $feature === 'exports' ? true : null;
    }

    public function subscriptionType(Workspace $workspace): string
    {
        return 'workspace:'.$workspace->public_id;
    }
}
