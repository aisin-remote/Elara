<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\User;
use App\Services\NotificationPreferenceService;
use Illuminate\Console\Command;

class SendDeadlineReminders extends Command
{
    protected $signature = 'orbitra:send-deadline-reminders';

    protected $description = 'Queue task deadline reminders due in about 24 hours';

    public function handle(NotificationPreferenceService $notifications): int
    {
        Task::query()
            ->whereNull('completed_at')
            ->whereBetween('due_at', [now()->addHours(23), now()->addHours(24)])
            ->with(['assignees', 'workspace'])
            ->chunkById(100, function ($tasks) use ($notifications) {
                foreach ($tasks as $task) {
                    $task->assignees->each(fn (User $recipient) => $notifications->notify(
                        $recipient,
                        $task->workspace,
                        'deadline_reminder',
                        'Task due tomorrow',
                        '“'.$task->title.'” is due in about 24 hours.',
                        route('app.tasks.show', $task),
                        ['task_public_id' => $task->public_id, 'due_at' => $task->due_at?->toIso8601String()],
                    ));
                }
            });

        return self::SUCCESS;
    }
}
