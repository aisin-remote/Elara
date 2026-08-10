@extends('layouts.app')

@section('title', 'Integrations')
@section('page-title', 'Settings')

@section('content')
    <div>
        @include('app.settings._navigation')
        <div class="flex flex-col justify-between gap-3 sm:flex-row sm:items-end"><div><p class="text-sm font-semibold text-orbit-600">Connected tools</p><h2 class="mt-1 text-3xl font-bold tracking-tight">Bring your workflow together</h2><p class="mt-2 max-w-2xl text-sm text-slate-500">OAuth tokens are encrypted at rest and every provider receives only the scopes needed for its Orbitra action.</p></div></div>

        @if ($errors->has('provider'))<x-alert variant="error" class="mt-5">{{ $errors->first('provider') }}</x-alert>@endif

        <div class="mt-7 grid gap-5 lg:grid-cols-2">
            @foreach ($providers as $provider)
                @php
                    $connection = $connections->get($provider->value);
                    $serviceKey = $provider->value === 'google_drive' ? 'google' : $provider->value;
                    $configured = filled(config("services.{$serviceKey}.client_id")) && filled(config("services.{$serviceKey}.client_secret"));
                    $accent = match($provider->value) { 'slack' => 'from-fuchsia-500 to-rose-500', 'google_drive' => 'from-emerald-500 to-sky-500', 'github' => 'from-slate-700 to-slate-950', default => 'from-sky-500 to-indigo-500' };
                @endphp
                <article class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                    <div class="flex items-start justify-between gap-4 p-6"><div class="flex gap-4"><div class="grid size-12 shrink-0 place-items-center rounded-2xl bg-gradient-to-br {{ $accent }} text-lg font-black text-white">{{ str($provider->label())->substr(0, 1) }}</div><div><h3 class="text-xl font-bold">{{ $provider->label() }}</h3><p class="mt-1 text-sm text-slate-500">@switch($provider->value)@case('slack')Send project updates to a team channel.@break @case('google_drive')Attach verified Drive files to projects.@break @case('github')Link commits and pull requests to tasks.@break @default Create Zoom meetings for schedule events.@endswitch</p></div></div>@if($connection)<span class="rounded-full px-3 py-1 text-xs font-bold {{ $connection->status === 'connected' ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300' }}">{{ str($connection->status)->headline() }}</span>@endif</div>

                    @if (! $connection)
                        <div class="border-t border-slate-100 bg-slate-50 p-6 dark:border-slate-800 dark:bg-slate-950/40">
                            @if($configured)<a href="{{ route('internal.integrations.redirect', ['provider' => $provider->value, 'workspace_public_id' => $workspace->public_id]) }}" class="inline-flex min-h-11 items-center justify-center rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white dark:bg-orbit-500 dark:text-slate-950">Connect {{ $provider->label() }}</a>@else<x-button type="button" disabled>Connect {{ $provider->label() }}</x-button><p class="mt-2 text-xs text-amber-700 dark:text-amber-300">Add this provider’s OAuth credentials to enable connection.</p>@endif
                        </div>
                    @else
                        <div class="border-t border-slate-100 p-6 dark:border-slate-800">
                            <div class="mb-5 flex flex-col gap-2 rounded-2xl bg-slate-50 p-4 text-sm dark:bg-slate-800/60"><div class="flex justify-between gap-4"><span class="text-slate-500">Account</span><strong class="text-right">{{ $connection->account_name ?: $connection->external_account_id }}</strong></div><div class="flex justify-between gap-4"><span class="text-slate-500">Last sync</span><strong>{{ $connection->last_synced_at?->diffForHumans() ?: 'Never' }}</strong></div>@if($connection->error_message)<p class="text-rose-600">{{ $connection->error_message }}</p>@endif</div>

                            <form method="POST" action="{{ route('internal.integrations.action', $connection) }}" class="space-y-4">@csrf
                                @switch($provider->value)
                                    @case('slack')
                                        <div><x-label for="slack_channel">Channel ID or name</x-label><x-input id="slack_channel" name="channel" value="{{ data_get($connection->settings_json, 'channel') }}" placeholder="#product-updates" required /></div>
                                        <div><x-label for="slack_message">Message</x-label><x-textarea id="slack_message" name="message" rows="3" required>Orbitra update: {{ $workspace->name }} is connected and ready.</x-textarea></div>
                                        <x-button>Send test notification</x-button>
                                        @break
                                    @case('google_drive')
                                        <div><x-label for="drive_project">Project</x-label><x-select id="drive_project" name="project_public_id" required>@foreach($projects as $project)<option value="{{ $project->public_id }}">{{ $project->name }}</option>@endforeach</x-select></div>
                                        <div><x-label for="drive_file_id">Google Drive file ID</x-label><x-input id="drive_file_id" name="file_id" placeholder="Paste the ID from a Drive file URL" required /></div>
                                        <x-button>Verify and link file</x-button>
                                        @break
                                    @case('github')
                                        <div><x-label for="github_task">Task</x-label><x-select id="github_task" name="task_public_id" required>@foreach($tasks as $task)<option value="{{ $task->public_id }}">{{ $task->project->name }} · {{ $task->title }}</option>@endforeach</x-select></div>
                                        <div><x-label for="github_repository">Repository</x-label><x-input id="github_repository" name="repository" placeholder="owner/repository" required /></div>
                                        <div><x-label for="github_url">Commit or pull request URL</x-label><x-input id="github_url" name="url" type="url" placeholder="https://github.com/owner/repository/pull/42" required /></div>
                                        <x-button>Verify and link</x-button>
                                        @break
                                    @case('zoom')
                                        <div><x-label for="zoom_event">Schedule event</x-label><x-select id="zoom_event" name="schedule_event_public_id" required>@foreach($scheduleEvents as $event)<option value="{{ $event->public_id }}">{{ $event->title }} · {{ $event->start_at->format('M j, H:i') }}</option>@endforeach</x-select></div>
                                        <div><x-label for="zoom_topic">Meeting topic</x-label><x-input id="zoom_topic" name="topic" value="{{ $workspace->name }} sync" required /></div>
                                        <x-button>Create Zoom meeting</x-button>
                                        @break
                                @endswitch
                            </form>

                            @if($connection->links->isNotEmpty())<div class="mt-6 border-t border-slate-100 pt-5 dark:border-slate-800"><p class="text-xs font-bold uppercase tracking-wide text-slate-400">Recent linked resources</p><div class="mt-3 space-y-2">@foreach($connection->links->sortByDesc('created_at')->take(4) as $link)<a href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" class="flex items-center justify-between gap-3 rounded-xl bg-slate-50 px-3 py-2 text-sm hover:text-orbit-700 dark:bg-slate-800"><span class="truncate">{{ $link->name }}</span><span aria-hidden="true">↗</span></a>@endforeach</div></div>@endif

                            <form method="POST" action="{{ route('internal.integrations.destroy', $connection) }}" class="mt-6 border-t border-slate-100 pt-5 dark:border-slate-800">@csrf @method('DELETE')<x-button variant="danger">Disconnect {{ $provider->label() }}</x-button></form>
                        </div>
                    @endif
                </article>
            @endforeach
        </div>
    </div>
@endsection
