<?php

namespace App\Actions\Integration;

use App\Models\ActivityLog;
use App\Models\IntegrationConnection;
use App\Models\User;
use App\Services\IntegrationService;
use Illuminate\Support\Facades\DB;

class DisconnectIntegration
{
    public function __construct(private readonly IntegrationService $integrations) {}

    public function handle(IntegrationConnection $connection, User $actor, ?string $ipAddress = null): void
    {
        $this->integrations->revoke($connection);

        DB::transaction(function () use ($connection, $actor, $ipAddress): void {
            ActivityLog::record($connection->workspace, $connection, 'integration.disconnected', $actor, ['provider' => $connection->provider->value], $ipAddress);
            $connection->delete();
        });
    }
}
