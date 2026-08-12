<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const LEGACY = [
        ['To Do', 'Outstanding', 'todo', '#6366f1', 1024],
        ['In Progress', 'In Progress', 'in_progress', '#f59e0b', 2048],
        ['Backlog', 'Pending', 'backlog', '#94a3b8', 3072],
        ['Completed', 'Done', 'completed', '#10b981', 4096],
    ];

    public function up(): void
    {
        $this->replaceProjectStatuses(self::LEGACY);
        $this->replaceTemplates(self::LEGACY);
    }

    public function down(): void
    {
        $legacy = array_map(
            fn (array $status): array => [$status[1], $status[0], $status[2], $status[3], match ($status[0]) {
                'Backlog' => 1024,
                'To Do' => 2048,
                'In Progress' => 3072,
                default => 4096,
            }],
            self::LEGACY,
        );

        $this->replaceProjectStatuses($legacy);
        $this->replaceTemplates($legacy);
    }

    private function replaceProjectStatuses(array $statuses): void
    {
        foreach (DB::table('projects')->pluck('id') as $projectId) {
            $hasLegacyDefaults = DB::table('task_statuses')
                ->where('project_id', $projectId)
                ->where('is_system', true)
                ->whereIn('name', array_column($statuses, 0))
                ->exists();

            if (! $hasLegacyDefaults) {
                continue;
            }

            DB::transaction(function () use ($projectId, $statuses): void {
                foreach ($statuses as [$from, $to, $category, $color, $position]) {
                    $source = DB::table('task_statuses')
                        ->where('project_id', $projectId)
                        ->where('name', $from)
                        ->orderByDesc('is_system')
                        ->first();
                    $target = DB::table('task_statuses')
                        ->where('project_id', $projectId)
                        ->where('name', $to)
                        ->first();

                    if ($target && $source && $target->id !== $source->id) {
                        DB::table('tasks')->where('status_id', $source->id)->update(['status_id' => $target->id]);
                        DB::table('task_statuses')->where('id', $source->id)->update(['archived_at' => now(), 'updated_at' => now()]);
                    }

                    $statusId = $target?->id ?? $source?->id;
                    if (! $statusId) {
                        $statusId = DB::table('task_statuses')->insertGetId([
                            'public_id' => (string) Str::ulid(),
                            'project_id' => $projectId,
                            'name' => $to,
                            'color' => $color,
                            'category' => $category,
                            'position' => $position,
                            'is_system' => true,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('task_statuses')->where('id', $statusId)->update([
                        'name' => $to,
                        'category' => $category,
                        'color' => $color,
                        'position' => $position,
                        'archived_at' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }

    private function replaceTemplates(array $statuses): void
    {
        foreach (DB::table('workspaces')->pluck('id') as $workspaceId) {
            $hasLegacyDefaults = DB::table('task_status_templates')
                ->where('workspace_id', $workspaceId)
                ->whereIn('name', array_column($statuses, 0))
                ->exists();

            if (! $hasLegacyDefaults) {
                continue;
            }

            DB::transaction(function () use ($workspaceId, $statuses): void {
                foreach ($statuses as [$from, $to, $category, $color, $position]) {
                    $source = DB::table('task_status_templates')->where('workspace_id', $workspaceId)->where('name', $from)->first();
                    $target = DB::table('task_status_templates')->where('workspace_id', $workspaceId)->where('name', $to)->first();

                    if ($target && $source && $target->id !== $source->id) {
                        DB::table('task_status_templates')->where('id', $source->id)->update(['archived_at' => now(), 'updated_at' => now()]);
                    }

                    $templateId = $target?->id ?? $source?->id;
                    if (! $templateId) {
                        $templateId = DB::table('task_status_templates')->insertGetId([
                            'public_id' => (string) Str::ulid(),
                            'workspace_id' => $workspaceId,
                            'name' => $to,
                            'color' => $color,
                            'category' => $category,
                            'position' => $position,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                    }

                    DB::table('task_status_templates')->where('id', $templateId)->update([
                        'name' => $to,
                        'category' => $category,
                        'color' => $color,
                        'position' => $position,
                        'archived_at' => null,
                        'updated_at' => now(),
                    ]);
                }
            });
        }
    }
};
