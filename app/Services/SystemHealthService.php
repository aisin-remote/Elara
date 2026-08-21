<?php

namespace App\Services;

use App\Enums\BreakdownStatus;
use App\Enums\WorkspaceRole;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\TaskBreakdown;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceHoliday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SystemHealthService
{
    public function __construct(
        private readonly OrganizationDirectory $directory,
        private readonly DepartmentWorkspaceService $departmentWorkspaces,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        $organization = $this->organizationStatus();
        $mapping = $this->workspaceMapping($organization['departments'] ?? null);
        $queue = $this->queueStatus();
        $ai = $this->aiStatus();

        $health = [
            'organization' => collect($organization)->except('departments')->all(),
            'user_sync' => $this->userSyncStatus(),
            'database' => $this->databaseStatus(),
            'storage' => $this->storageStatus(),
            'scheduler' => $this->schedulerStatus(),
            'queue' => collect($queue)->except('failed_jobs')->all(),
            'ai' => collect($ai)->except('failed_breakdowns')->all(),
            'openai' => $this->openAiStatus(),
            'holidays' => $this->holidayStatus(),
            'mapping' => $mapping,
            'failed_jobs' => $queue['failed_jobs'],
            'failed_breakdowns' => $ai['failed_breakdowns'],
            'last_integrity_check' => Cache::get('system_health.integrity_check'),
        ];

        $health['diagnostic_report'] = $this->diagnosticReport($health);

        return $health;
    }

    public function retryFailedJob(string $uuid): string
    {
        $table = (string) config('queue.failed.table', 'failed_jobs');

        if (! Schema::hasTable($table) || ! DB::table($table)->where('uuid', $uuid)->exists()) {
            throw new RuntimeException('The failed job no longer exists.');
        }

        $this->runCommand('queue:retry', ['id' => [$uuid]]);

        return 'The failed job was returned to the queue.';
    }

    public function retryBreakdown(string $publicId): string
    {
        $breakdown = TaskBreakdown::where('public_id', $publicId)->first();

        if (! $breakdown || $breakdown->status !== BreakdownStatus::FAILED) {
            throw new RuntimeException('The failed AI breakdown no longer exists.');
        }

        $subject = $breakdown->subject;

        if (! $subject) {
            throw new RuntimeException('The source request for this breakdown has been removed.');
        }

        $breakdown->update([
            'status' => BreakdownStatus::PENDING,
            'error_message' => null,
        ]);
        GenerateTaskBreakdown::dispatch($subject);

        return 'The AI breakdown was queued again.';
    }

    public function syncOrganizationUsers(): string
    {
        $this->assertOrganizationAvailable();
        $synced = 0;

        User::where('auth_source', 'organization')->chunkById(100, function ($users) use (&$synced): void {
            foreach ($users as $user) {
                $profile = $this->directory->profile($user);

                if (! $profile) {
                    continue;
                }

                $name = preg_split('/\s+/', trim((string) ($profile['name'] ?? '')), 2) ?: [];
                $identity = [
                    'organization_user_id' => $profile['organization_user_id'],
                    'organization_synced_at' => now(),
                ];

                if (($name[0] ?? '') !== '') {
                    $identity['first_name'] = $name[0];
                    $identity['last_name'] = $name[1] ?? '';
                }

                $user->forceFill($identity)->save();
                $synced++;
            }
        });

        return "Synced {$synced} organization user profile(s).";
    }

    public function rebuildMemberships(): string
    {
        $this->assertOrganizationAvailable();
        $rebuilt = 0;

        User::where('auth_source', 'organization')->chunkById(100, function ($users) use (&$rebuilt): void {
            foreach ($users as $user) {
                $profile = $this->directory->profile($user);
                $hasProtectedRole = $user->workspaceMemberships()
                    ->active()
                    ->whereIn('role', [WorkspaceRole::OWNER->value, WorkspaceRole::ADMIN->value])
                    ->exists();

                if (! $profile || $hasProtectedRole) {
                    continue;
                }

                $this->departmentWorkspaces->syncMembership(
                    $user,
                    $profile,
                    $this->directory->workspaceRole($profile),
                );
                $user->forceFill(['organization_synced_at' => now()])->save();
                $rebuilt++;
            }
        });

        return "Rebuilt {$rebuilt} department workspace membership(s).";
    }

    public function drainApprovedRequests(): string
    {
        $this->runCommand('orbitra:drain-request-queue');

        return trim(Artisan::output()) ?: 'The approved request queue was drained.';
    }

    public function runIntegrityCheck(): string
    {
        $snapshot = $this->snapshot();
        $orphanedBreakdowns = TaskBreakdown::query()
            ->with('subject')
            ->get()
            ->filter(fn (TaskBreakdown $breakdown): bool => $breakdown->subject === null)
            ->count();
        $issues = (int) ($snapshot['mapping']['issue_count'] ?? 0) + $orphanedBreakdowns;
        $result = [
            'status' => $issues === 0 ? 'healthy' : 'warning',
            'at' => now()->toIso8601String(),
            'issue_count' => $issues,
            'message' => $issues === 0
                ? 'No referential or department mapping issues were found.'
                : "Found {$issues} issue(s): ".($snapshot['mapping']['issue_count'] ?? 0).' department mapping and '.$orphanedBreakdowns.' orphaned AI breakdown.',
        ];

        Cache::forever('system_health.integrity_check', $result);

        return $result['message'];
    }

    /** @return array<string, mixed> */
    private function organizationStatus(): array
    {
        if (! config('organization.required')) {
            return $this->status('disabled', 'Organization directory is disabled in this environment.', ['departments' => null]);
        }

        try {
            $connection = DB::connection((string) config('organization.connection'));
            $connection->select('select 1');
            $departments = $connection->table('departments')->select('id', 'code', 'name')->orderBy('name')->get();

            return $this->status('healthy', 'PostgreSQL organization directory is reachable.', [
                'department_count' => $departments->count(),
                'departments' => $departments,
            ]);
        } catch (Throwable $exception) {
            return $this->status('failed', 'PostgreSQL organization directory is unreachable.', [
                'error' => Str::limit($exception->getMessage(), 180),
                'departments' => null,
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function databaseStatus(): array
    {
        try {
            DB::connection()->select('select 1');

            return $this->status('healthy', 'Application database is reachable.', [
                'driver' => DB::connection()->getDriverName(),
            ]);
        } catch (Throwable $exception) {
            return $this->status('failed', 'Application database is unreachable.', [
                'error' => Str::limit($exception->getMessage(), 180),
            ]);
        }
    }

    /** @return array<string, mixed> */
    private function storageStatus(): array
    {
        $path = storage_path('app');
        $writable = is_dir($path) && is_writable($path);
        $freeBytes = @disk_free_space(storage_path());

        return $this->status($writable ? 'healthy' : 'failed', $writable
            ? 'Application storage is writable.'
            : 'Application storage is not writable.', [
                'free_space' => is_numeric($freeBytes) ? $this->formatBytes((float) $freeBytes) : 'Unknown',
            ]);
    }

    /** @return array<string, mixed> */
    private function schedulerStatus(): array
    {
        $value = Cache::get('system_health.scheduler_last_seen_at');
        $lastSeen = is_string($value) ? CarbonImmutable::parse($value) : null;
        $fresh = $lastSeen?->greaterThan(now()->subMinutes(3)) ?? false;

        return $this->status($lastSeen ? ($fresh ? 'healthy' : 'warning') : 'unknown', match (true) {
            $lastSeen === null => 'No scheduler heartbeat has been recorded yet.',
            $fresh => 'Scheduler heartbeat is current.',
            default => 'Scheduler heartbeat is delayed.',
        }, ['last_seen_at' => $lastSeen?->toIso8601String()]);
    }

    /** @return array<string, mixed> */
    private function queueStatus(): array
    {
        $jobsTable = (string) config('queue.connections.database.table', 'jobs');
        $failedTable = (string) config('queue.failed.table', 'failed_jobs');
        $pending = Schema::hasTable($jobsTable) ? DB::table($jobsTable)->count() : 0;
        $failed = Schema::hasTable($failedTable) ? DB::table($failedTable)->count() : 0;
        $failedJobs = Schema::hasTable($failedTable)
            ? DB::table($failedTable)->latest('failed_at')->limit(10)->get()->map(function ($job): array {
                $payload = json_decode((string) $job->payload, true);

                return [
                    'uuid' => $job->uuid,
                    'name' => Str::afterLast((string) ($payload['displayName'] ?? 'Queued job'), '\\'),
                    'queue' => $job->queue,
                    'failed_at' => $job->failed_at,
                ];
            })->all()
            : [];

        return $this->status($failed > 0 ? 'warning' : 'healthy', $failed > 0
            ? "{$failed} job(s) need attention."
            : 'No failed jobs.', compact('pending', 'failed') + ['failed_jobs' => $failedJobs]);
    }

    /** @return array<string, mixed> */
    private function aiStatus(): array
    {
        $latest = TaskBreakdown::latest('updated_at')->first();
        $lastSuccess = TaskBreakdown::whereIn('status', [BreakdownStatus::READY->value, BreakdownStatus::ACCEPTED->value])
            ->latest('updated_at')
            ->first();
        $lastFailure = TaskBreakdown::where('status', BreakdownStatus::FAILED->value)->latest('updated_at')->first();
        $failedBreakdowns = TaskBreakdown::where('status', BreakdownStatus::FAILED->value)
            ->with(['workspace', 'subject'])
            ->latest('updated_at')
            ->limit(10)
            ->get()
            ->map(fn (TaskBreakdown $breakdown): array => [
                'public_id' => $breakdown->public_id,
                'workspace' => $breakdown->workspace?->name ?? 'Removed workspace',
                'subject' => $breakdown->subject?->title ?? $breakdown->subject?->name ?? 'Removed source',
                'error' => Str::limit((string) $breakdown->error_message, 140),
                'failed_at' => $breakdown->updated_at?->toIso8601String(),
            ])->all();

        $status = match (true) {
            $latest === null => 'unknown',
            $lastFailure && (! $lastSuccess || $lastFailure->updated_at->greaterThan($lastSuccess->updated_at)) => 'warning',
            default => 'healthy',
        };

        return $this->status($status, match (true) {
            $latest === null => 'No AI breakdown has run yet.',
            $latest->status === BreakdownStatus::FAILED => 'The latest AI breakdown failed.',
            default => 'The latest AI breakdown completed without a recorded failure.',
        }, [
            'last_run_at' => $latest?->updated_at?->toIso8601String(),
            'last_status' => $latest?->status?->value,
            'last_success_at' => $lastSuccess?->updated_at?->toIso8601String(),
            'last_failure_at' => $lastFailure?->updated_at?->toIso8601String(),
            'failed_breakdowns' => $failedBreakdowns,
        ]);
    }

    /** @return array<string, mixed> */
    private function openAiStatus(): array
    {
        $configured = filled(config('services.openai.key'));

        return $this->status($configured ? 'healthy' : 'warning', $configured
            ? 'OpenAI credentials are configured.'
            : 'OPENAI_API_KEY is not configured.', [
                'model' => config('services.openai.model'),
                'endpoint' => config('services.openai.base_url'),
            ]);
    }

    /** @return array<string, mixed> */
    private function holidayStatus(): array
    {
        $run = Cache::get('system_health.holiday_sync');
        $lastRow = WorkspaceHoliday::max('updated_at');

        if (is_array($run)) {
            return $this->status($run['status'] ?? 'unknown', $run['message'] ?? 'Holiday sync status is unavailable.', [
                'last_run_at' => $run['at'] ?? null,
            ]);
        }

        return $this->status($lastRow ? 'unknown' : 'warning', $lastRow
            ? 'Holiday data exists, but no scheduler run has been recorded yet.'
            : 'No holiday data or sync run has been recorded.', [
                'last_run_at' => $lastRow,
            ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function workspaceMapping(mixed $departments): array
    {
        if ($departments === null) {
            return $this->status(config('organization.required') ? 'failed' : 'disabled', config('organization.required')
                ? 'Workspace mapping cannot be checked while PostgreSQL is unavailable.'
                : 'Workspace mapping is not required in this environment.', ['issue_count' => 0, 'issues' => []]);
        }

        $departments = collect($departments);
        $workspaces = Workspace::whereNotNull('organization_department_id')->get();
        $departmentIds = $departments->pluck('id')->map(fn ($id): int => (int) $id);
        $missing = $departments->reject(fn ($department) => $workspaces->contains('organization_department_id', (int) $department->id));
        $stale = $workspaces->reject(fn (Workspace $workspace) => $departmentIds->contains((int) $workspace->organization_department_id));
        $duplicates = $workspaces->groupBy('organization_department_id')
            ->map(fn ($items): int => $items->count())
            ->filter(fn (int $count): bool => $count > 1);
        $mismatched = $workspaces->filter(function (Workspace $workspace) use ($departments): bool {
            $department = $departments->firstWhere('id', $workspace->organization_department_id);

            return $department && strcasecmp((string) $department->code, (string) $workspace->organization_department_code) !== 0;
        });
        $issues = [
            ...$missing->map(fn ($department): string => "Missing workspace: {$department->code} — {$department->name}")->all(),
            ...$stale->map(fn (Workspace $workspace): string => "Unknown department mapping: {$workspace->name}")->all(),
            ...$duplicates->map(fn (int $count, $id): string => "Department {$id} is mapped to {$count} workspaces")->values()->all(),
            ...$mismatched->map(fn (Workspace $workspace): string => "Department code mismatch: {$workspace->name}")->all(),
        ];

        return $this->status($issues === [] ? 'healthy' : 'warning', $issues === []
            ? 'Every organization department has one matching workspace.'
            : count($issues).' workspace mapping issue(s) found.', [
                'issue_count' => count($issues),
                'issues' => array_slice($issues, 0, 20),
            ]);
    }

    /** @return array<string, mixed> */
    private function userSyncStatus(): array
    {
        $last = User::whereNotNull('organization_synced_at')->max('organization_synced_at');
        $managed = User::where('auth_source', 'organization')->count();

        return $this->status($last ? 'healthy' : ($managed > 0 ? 'warning' : 'unknown'), $last
            ? 'Organization users have been synchronized.'
            : 'No organization user sync has been recorded.', [
                'last_run_at' => $last,
                'managed_users' => $managed,
            ]);
    }

    /** @param array<string, mixed> $health */
    private function diagnosticReport(array $health): string
    {
        $lines = [
            'ELARA SYSTEM HEALTH',
            'Generated: '.now()->toIso8601String(),
            'Environment: '.app()->environment(),
            '',
        ];

        foreach (['organization', 'user_sync', 'database', 'storage', 'scheduler', 'queue', 'ai', 'openai', 'holidays', 'mapping'] as $key) {
            $item = $health[$key];
            $lines[] = strtoupper(str_replace('_', ' ', $key)).': '.strtoupper((string) $item['status']).' — '.$item['message'];
        }

        $lines[] = 'Queue: '.($health['queue']['pending'] ?? 0).' pending, '.($health['queue']['failed'] ?? 0).' failed';
        $lines[] = 'Workspace mapping issues: '.($health['mapping']['issue_count'] ?? 0);

        return implode(PHP_EOL, $lines);
    }

    private function assertOrganizationAvailable(): void
    {
        $status = $this->organizationStatus();

        if ($status['status'] !== 'healthy') {
            throw new RuntimeException($status['message']);
        }
    }

    /** @param array<string, mixed> $arguments */
    private function runCommand(string $command, array $arguments = []): void
    {
        if (Artisan::call($command, $arguments) !== 0) {
            throw new RuntimeException(trim(Artisan::output()) ?: "The {$command} command failed.");
        }
    }

    /** @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    private function status(string $status, string $message, array $extra = []): array
    {
        return ['status' => $status, 'message' => $message, ...$extra];
    }

    private function formatBytes(float $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log(max($bytes, 1), 1024)), count($units) - 1);

        return round($bytes / (1024 ** $power), 1).' '.$units[$power];
    }
}
