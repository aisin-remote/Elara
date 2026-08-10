<?php

namespace App\Actions\Integration;

use App\Enums\IntegrationProvider;
use App\Models\ActivityLog;
use App\Models\IntegrationConnection;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanEntitlementService;
use Illuminate\Support\Facades\DB;

class ConnectIntegration
{
    public function __construct(private readonly PlanEntitlementService $entitlements) {}

    public function handle(Workspace $workspace, User $actor, IntegrationProvider $provider, array $credentials, ?string $ipAddress = null): IntegrationConnection
    {
        $existing = $workspace->integrationConnections()
            ->where('provider', $provider->value)
            ->where('external_account_id', $credentials['external_account_id'])
            ->first();

        if (! $existing) {
            $this->entitlements->assertCanConnectIntegration($workspace);
        }

        return DB::transaction(function () use ($workspace, $actor, $provider, $credentials, $existing, $ipAddress): IntegrationConnection {
            $connection = $existing ?? new IntegrationConnection(['workspace_id' => $workspace->id]);
            $connection->fill([
                'provider' => $provider,
                'external_account_id' => $credentials['external_account_id'],
                'account_name' => $credentials['account_name'] ?? null,
                'access_token' => $credentials['access_token'],
                'refresh_token' => $credentials['refresh_token'] ?? null,
                'expires_at' => isset($credentials['expires_in']) ? now()->addSeconds((int) $credentials['expires_in']) : null,
                'scopes_json' => $credentials['scopes'] ?? [],
                'status' => 'connected',
                'error_message' => null,
                'last_synced_at' => now(),
            ])->save();

            ActivityLog::record($workspace, $connection, 'integration.connected', $actor, ['provider' => $provider->value], $ipAddress);

            return $connection;
        });
    }
}
