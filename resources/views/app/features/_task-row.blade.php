<a href="{{ route('app.tasks.show', $task->public_id) }}" class="flex flex-wrap items-center gap-3 p-4 transition hover:bg-slate-50 dark:hover:bg-slate-800/60">
    <span class="size-2.5 shrink-0 rounded-full" style="background-color: {{ $task->status->color ?: '#64748b' }}"></span>
    <span class="min-w-0 flex-1">
        <span class="block truncate text-sm font-semibold">{{ $task->title }}</span>
        <span class="mt-0.5 block text-xs text-slate-500">
            {{ $task->status->name }}
            @if ($task->due_at)
                · due {{ $task->due_at->format('M j') }}
                @if (! $task->completed_at && $task->due_at->isPast())
                    <span class="font-semibold text-rose-600 dark:text-rose-400">· overdue</span>
                @endif
            @endif
        </span>
    </span>
    <span class="flex shrink-0 -space-x-1.5">
        @foreach ($task->assignees->take(3) as $assignee)
            <x-avatar :src="filled($assignee->avatar_path) ? route('internal.users.avatar', $assignee) : null" :name="$assignee->name" size="size-6" class="border-2 border-white dark:border-slate-900" />
        @endforeach
    </span>
</a>
