<?php

namespace App\Services;

use App\Enums\BreakdownStatus;
use App\Enums\CheckpointStatus;
use App\Enums\FeatureRequestStatus;
use App\Enums\OrganizationRankGroup;
use App\Enums\ProjectRequestStatus;
use App\Enums\TaskStatusCategory;
use App\Models\ActivityLog;
use App\Models\FeatureRequest;
use App\Models\ProjectRequest;
use App\Models\Task;
use App\Models\TaskBreakdown;
use App\Models\ValidationCheckpoint;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RequestProgressService
{
    public function build(FeatureRequest|ProjectRequest $request): array
    {
        $tasks = $this->tasks($request);
        $checkpoints = ValidationCheckpoint::query()
            ->where('subject_type', $request->getMorphClass())
            ->where('subject_id', $request->id)
            ->oldest('opened_at')
            ->get();
        $openCheckpoints = $checkpoints->where('status', CheckpointStatus::OPEN);
        $breakdown = $request->breakdowns()->latest('id')->first();
        $logs = ActivityLog::query()
            ->where('subject_type', $request->getMorphClass())
            ->where('subject_id', $request->id)
            ->oldest('created_at')
            ->get();

        $current = $request instanceof FeatureRequest
            ? $this->featureStage($request, $tasks, $checkpoints, $openCheckpoints)
            : $this->projectStage($request, $tasks, $checkpoints, $openCheckpoints);
        $stopped = in_array($request->status->value, ['rejected', 'taken_down'], true);
        $attention = $request->status->value === 'needs_info' || $openCheckpoints->isNotEmpty();
        $definitions = $request instanceof FeatureRequest
            ? $this->featureStages($request, $tasks, $checkpoints, $logs, $breakdown)
            : $this->projectStages($request, $tasks, $checkpoints, $logs, $breakdown);

        $completed = $tasks->whereNotNull('completed_at')->count();
        $blocked = $tasks->filter(fn (Task $task) => $task->completed_at === null && $task->isBlocked())->count();
        $progress = $tasks->isEmpty()
            ? ($request->status->value === 'delivered' ? 100 : 0)
            : (int) round($tasks->average(fn (Task $task) => $task->checklist_total > 0
                ? ($task->checklist_completed / $task->checklist_total) * 100
                : ($task->completed_at ? 100 : 0)));
        $timezone = $request->workspace->timezone ?: config('app.timezone');
        $updated = collect([
            $request->updated_at,
            $tasks->max('updated_at'),
            $checkpoints->max('updated_at'),
            $breakdown?->updated_at,
        ])->filter()->sortByDesc(fn (CarbonInterface $date) => $date->getTimestamp())->first();

        return [
            'current_stage' => $this->currentLabel($request, $definitions[$current]['label']),
            'description' => $this->description($request, $tasks, $openCheckpoints, $breakdown, $blocked),
            'status' => $request->status->value,
            'progress' => $progress,
            'tasks' => ['completed' => $completed, 'total' => $tasks->count(), 'blocked' => $blocked],
            'validations' => [
                'open' => $openCheckpoints->count(),
                'deadline' => $openCheckpoints->sortBy('expires_at')->first()?->countdown(),
            ],
            'assignee' => $request->assignee?->name,
            'schedule' => $this->scheduleLabel($request, $timezone),
            'action' => $openCheckpoints->isNotEmpty()
                ? ['label' => 'Buka validasi', 'url' => route('desk.validations.index')]
                : null,
            'stages' => collect($definitions)->map(function (array $stage, int $index) use ($current, $stopped, $attention, $request, $timezone) {
                $isCurrent = $index === $current;
                $delivered = $request->status->value === 'delivered';

                return [
                    ...$stage,
                    'state' => $index < $current || ($delivered && $index === $current)
                        ? 'completed'
                        : ($index > $current ? 'upcoming' : ($stopped ? 'failed' : ($attention ? 'attention' : 'current'))),
                    'is_current' => $isCurrent,
                    'time' => $stage['at']?->toIso8601String(),
                    'time_label' => $stage['at']?->setTimezone($timezone)->format('j M Y, H:i'),
                ];
            })->map(fn (array $stage) => collect($stage)->except('at')->all())->values()->all(),
            'updated_at' => $updated?->toIso8601String(),
            'updated_label' => $updated?->setTimezone($timezone)->diffForHumans(),
        ];
    }

    private function tasks(FeatureRequest|ProjectRequest $request): Collection
    {
        if ($request instanceof FeatureRequest && ! $request->feature_id) {
            return collect();
        }

        if ($request instanceof ProjectRequest && ! $request->project_id) {
            return collect();
        }

        return Task::query()
            ->where($request instanceof FeatureRequest ? 'feature_id' : 'project_id', $request instanceof FeatureRequest ? $request->feature_id : $request->project_id)
            ->whereNull('archived_at')
            ->whereHas('status', fn ($status) => $status->where('category', '!=', TaskStatusCategory::CANCELLED->value))
            ->with('dependencies:id,completed_at')
            ->withCount([
                'checklistItems as checklist_total',
                'checklistItems as checklist_completed' => fn ($items) => $items->where('is_completed', true),
            ])
            ->get();
    }

    private function featureStage(FeatureRequest $request, Collection $tasks, Collection $checkpoints, Collection $open): int
    {
        $offset = $this->requiresDepartmentApproval($request) ? 1 : 0;

        if ($request->status === FeatureRequestStatus::DELIVERED) {
            return 6 + $offset;
        }

        if ($request->status === FeatureRequestStatus::REJECTED) {
            return $request->reviewed_at ? 1 + $offset : 1;
        }

        if ($request->status === FeatureRequestStatus::TAKEN_DOWN) {
            return $checkpoints->isNotEmpty() ? 5 + $offset : ($tasks->isNotEmpty() ? 4 + $offset : 2 + $offset);
        }

        if ($open->isNotEmpty()) {
            return 5 + $offset;
        }

        if ($tasks->isNotEmpty() && $tasks->every(fn (Task $task) => $task->completed_at !== null)) {
            return 6 + $offset;
        }

        if ($tasks->isNotEmpty() || $request->status === FeatureRequestStatus::IN_PROGRESS) {
            return 4 + $offset;
        }

        return match ($request->status) {
            FeatureRequestStatus::SCHEDULED => 3 + $offset,
            FeatureRequestStatus::APPROVED => 2 + $offset,
            FeatureRequestStatus::PENDING_DEPARTMENT => 1,
            FeatureRequestStatus::PENDING_REVIEW => 1 + $offset,
            FeatureRequestStatus::NEEDS_INFO => $request->needs_info_stage === 'department' ? 1 : 1 + $offset,
            default => 0,
        };
    }

    private function projectStage(ProjectRequest $request, Collection $tasks, Collection $checkpoints, Collection $open): int
    {
        $offset = $this->requiresDepartmentApproval($request) ? 1 : 0;

        if ($request->status === ProjectRequestStatus::DELIVERED) {
            return 8 + $offset;
        }

        if ($request->status === ProjectRequestStatus::REJECTED) {
            return $request->manager_at ? 3 + $offset : ($request->meetingHeld() || $request->spv_at ? 2 + $offset : 1);
        }

        if ($request->status === ProjectRequestStatus::TAKEN_DOWN) {
            return $checkpoints->isNotEmpty() ? 7 + $offset : ($tasks->isNotEmpty() ? 6 + $offset : 4 + $offset);
        }

        if ($open->isNotEmpty()) {
            return 7 + $offset;
        }

        if ($tasks->isNotEmpty() && $tasks->every(fn (Task $task) => $task->completed_at !== null)) {
            return 8 + $offset;
        }

        if ($tasks->isNotEmpty() || $request->status === ProjectRequestStatus::IN_PROGRESS) {
            return 6 + $offset;
        }

        return match ($request->status) {
            ProjectRequestStatus::SCHEDULED => 5 + $offset,
            ProjectRequestStatus::APPROVED => 4 + $offset,
            ProjectRequestStatus::PENDING_MANAGER => 3 + $offset,
            ProjectRequestStatus::PENDING_SPV => 2 + $offset,
            ProjectRequestStatus::PENDING_DEPARTMENT => 1,
            ProjectRequestStatus::NEEDS_INFO => $request->needs_info_stage === 'department' ? 1 : 2 + $offset,
            ProjectRequestStatus::PENDING_MEETING => 1 + $offset,
            default => 0,
        };
    }

    private function featureStages(FeatureRequest $request, Collection $tasks, Collection $checkpoints, Collection $logs, ?TaskBreakdown $breakdown): array
    {
        $stages = [
            ['key' => 'submitted', 'label' => 'Diajukan', 'at' => $request->created_at],
            ['key' => 'review', 'label' => 'Review supervisor', 'at' => $this->actionTime($logs, ['feature_request.pending_review'])],
            ['key' => 'scheduling', 'label' => 'Penjadwalan', 'at' => $this->actionTime($logs, ['feature_request.approved'])],
            ['key' => 'planning', 'label' => 'Rencana kerja', 'at' => $this->actionTime($logs, ['feature_request.scheduled'])],
            ['key' => 'delivery', 'label' => 'Pengerjaan', 'at' => $breakdown?->accepted_at ?? $tasks->min('created_at')],
            ['key' => 'validation', 'label' => 'Validasi', 'at' => $checkpoints->min('opened_at')],
            ['key' => 'delivered', 'label' => 'Selesai', 'at' => $this->actionTime($logs, ['feature_request.delivered']) ?? $tasks->max('completed_at')],
        ];

        if ($this->requiresDepartmentApproval($request)) {
            array_splice($stages, 1, 0, [[
                'key' => 'department',
                'label' => 'Approval department',
                'at' => $request->department_reviewed_at,
            ]]);
        }

        return $stages;
    }

    private function projectStages(ProjectRequest $request, Collection $tasks, Collection $checkpoints, Collection $logs, ?TaskBreakdown $breakdown): array
    {
        $stages = [
            ['key' => 'submitted', 'label' => 'Diajukan', 'at' => $request->created_at],
            ['key' => 'scoping', 'label' => 'Rapat scoping', 'at' => $this->actionTime($logs, ['project_request.pending_meeting'])],
            ['key' => 'supervisor', 'label' => 'Approval supervisor', 'at' => $request->meeting_held_at],
            ['key' => 'manager', 'label' => 'Approval manager', 'at' => $request->spv_at],
            ['key' => 'scheduling', 'label' => 'Penjadwalan', 'at' => $request->manager_at],
            ['key' => 'planning', 'label' => 'Rencana kerja', 'at' => $this->actionTime($logs, ['project_request.scheduled'])],
            ['key' => 'delivery', 'label' => 'Pengerjaan', 'at' => $breakdown?->accepted_at ?? $tasks->min('created_at')],
            ['key' => 'validation', 'label' => 'Validasi', 'at' => $checkpoints->min('opened_at')],
            ['key' => 'delivered', 'label' => 'Selesai', 'at' => $this->actionTime($logs, ['project_request.delivered']) ?? $tasks->max('completed_at')],
        ];

        if ($this->requiresDepartmentApproval($request)) {
            array_splice($stages, 1, 0, [[
                'key' => 'department',
                'label' => 'Approval department',
                'at' => $request->department_reviewed_at,
            ]]);
        }

        return $stages;
    }

    private function description(FeatureRequest|ProjectRequest $request, Collection $tasks, Collection $open, ?TaskBreakdown $breakdown, int $blocked): string
    {
        if ($request->status->value === 'rejected') {
            return 'Permintaan berhenti pada proses persetujuan. Alasan keputusan ditampilkan di bawah timeline.';
        }

        if ($request->status->value === 'taken_down') {
            return 'Permintaan dihentikan setelah batas waktu validasi berakhir.';
        }

        if ($request->status->value === 'needs_info') {
            return 'Tim menunggu informasi tambahan dari Anda sebelum proses dilanjutkan.';
        }

        if ($open->isNotEmpty()) {
            return $open->count().' hasil kerja menunggu validasi Anda. Buka validasi sebelum batas waktunya.';
        }

        if ($tasks->isNotEmpty()) {
            $completed = $tasks->whereNotNull('completed_at')->count();

            return $completed.' dari '.$tasks->count().' task selesai'.($blocked ? '; '.$blocked.' task masih menunggu prerequisite.' : '.');
        }

        if ($breakdown?->status === BreakdownStatus::PENDING) {
            return 'AI sedang menyusun rencana kerja untuk ditinjau oleh tim.';
        }

        if ($breakdown?->status === BreakdownStatus::READY) {
            return 'Rencana kerja sudah siap dan sedang ditinjau oleh tim sebelum masuk ke board.';
        }

        return match ($request->status->value) {
            'pending_department' => 'Permintaan menunggu approval manager atau coordinator department Anda.',
            'pending_review' => 'Permintaan sedang menunggu keputusan supervisor.',
            'pending_meeting' => $request instanceof ProjectRequest && $request->meeting
                ? 'Rapat scoping sudah dijadwalkan dan menunggu untuk dilaksanakan.'
                : 'Supervisor akan menjadwalkan rapat scoping.',
            'pending_spv' => 'Rapat selesai dan permintaan menunggu persetujuan supervisor.',
            'pending_manager' => 'Supervisor sudah menyetujui; sekarang menunggu persetujuan manager.',
            'approved' => 'Permintaan disetujui dan sedang mencari slot kapasitas tim.',
            'scheduled' => 'PIC dan jadwal sudah ditentukan; tim sedang menyiapkan rencana kerja.',
            'delivered' => 'Seluruh pekerjaan dan validasi sudah selesai.',
            default => 'Permintaan sudah tercatat dan akan bergerak ke tahap berikutnya.',
        };
    }

    private function currentLabel(FeatureRequest|ProjectRequest $request, string $fallback): string
    {
        return match ($request->status->value) {
            'rejected' => 'Tidak disetujui',
            'taken_down' => 'Dihentikan',
            'delivered' => 'Selesai',
            'needs_info' => 'Menunggu informasi Anda',
            default => $fallback,
        };
    }

    private function scheduleLabel(FeatureRequest|ProjectRequest $request, string $timezone): ?string
    {
        $start = $request->scheduled_start?->setTimezone($timezone);
        $due = $request->scheduled_due?->setTimezone($timezone);

        if (! $start && ! $due) {
            return null;
        }

        if (! $start || ! $due || $start->isSameDay($due)) {
            return ($start ?? $due)->format('j M Y');
        }

        return $start->format('j M').' – '.$due->format('j M Y');
    }

    private function actionTime(Collection $logs, array $actions): ?CarbonInterface
    {
        return $logs->first(fn (ActivityLog $log) => in_array($log->action, $actions, true))?->created_at;
    }

    private function requiresDepartmentApproval(FeatureRequest|ProjectRequest $request): bool
    {
        return $request->requester_department_external_id !== null
            && strcasecmp((string) $request->requester_department_code, config('organization.it_department_code')) !== 0
            && OrganizationRankGroup::fromCode($request->requester_job_rank_code) !== OrganizationRankGroup::MANAGEMENT;
    }
}
