@extends('layouts.app')

@section('title', 'Team')
@section('page-title', 'Team')

@section('content')
    <div class="grid gap-4 sm:grid-cols-3">
        @foreach([['Team members', $summary['members'], 'team'], ['Online now', $summary['online'], 'performance'], ['Active tasks', $summary['tasks'], 'tasks']] as [$label, $value, $icon])
            <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"><div class="flex items-center justify-between"><div><p class="text-sm text-slate-500">{{ $label }}</p><p class="mt-2 text-3xl font-bold">{{ $value }}</p></div><span class="grid size-11 place-items-center rounded-xl bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-300"><x-icon :name="$icon" /></span></div></section>
        @endforeach
    </div>

    <form method="GET" action="{{ route('app.workspaces.team', $workspace) }}" class="mt-6 grid gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:grid-cols-2 xl:grid-cols-[1fr_180px_180px_220px_auto] dark:border-slate-800 dark:bg-slate-900">
        <label class="relative"><span class="sr-only">Search members</span><x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" /><input type="search" name="search" value="{{ request('search') }}" class="min-h-11 w-full rounded-xl border-slate-300 pl-9 text-sm dark:border-slate-700 dark:bg-slate-950" placeholder="Name or email…"></label>
        <x-select name="role" aria-label="Filter by role"><option value="">All roles</option>@foreach(App\Enums\WorkspaceRole::cases() as $role)<option value="{{ $role->value }}" @selected(request('role') === $role->value)>{{ $role->label() }}</option>@endforeach</x-select>
        <x-select name="presence" aria-label="Filter by presence"><option value="">Any presence</option><option value="active" @selected(request('presence') === 'active')>Online</option><option value="offline" @selected(request('presence') === 'offline')>Offline</option></x-select>
        <x-select name="project" aria-label="Filter by project"><option value="">All visible projects</option>@foreach($projects as $project)<option value="{{ $project->public_id }}" @selected(request('project') === $project->public_id)>{{ $project->name }}</option>@endforeach</x-select>
        <x-button>Filter</x-button>
    </form>

    <div class="mt-6 grid gap-6 xl:grid-cols-[1fr_360px]">
        <section aria-labelledby="members-title">
            <div class="flex items-end justify-between gap-4"><div><h2 id="members-title" class="text-xl font-bold">Workspace members</h2><p class="mt-1 text-sm text-slate-500">{{ $memberships->count() }} matching members</p></div>@if(request()->hasAny(['search','role','presence','project']))<a href="{{ route('app.workspaces.team', $workspace) }}" class="text-sm font-semibold text-orbit-700 dark:text-orbit-300">Clear filters</a>@endif</div>
            <div class="mt-4 grid gap-4 md:grid-cols-2">
                @php($maxWorkload = max(1, (int) $memberships->max('user.active_tasks_count')))
                @forelse ($memberships as $membership)
                    @php($online = $membership->user->last_seen_at?->gte(now()->subMinutes(5)) ?? false)
                    @php($currentProject = $membership->user->projectMemberships->first()?->project)
                    <article class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
                        <div class="flex items-start gap-4"><x-avatar :src="$membership->user->avatar_path ? route('internal.users.avatar', $membership->user) : null" :name="$membership->user->name" size="size-12" /><div class="min-w-0 flex-1"><div class="flex items-start justify-between gap-3"><div class="min-w-0"><h3 class="truncate font-bold">{{ $membership->user->name }}</h3><p class="truncate text-sm text-slate-500">{{ $membership->user->job_title ?: $membership->user->email }}</p></div><span class="mt-1 size-2.5 shrink-0 rounded-full {{ $online ? 'bg-emerald-500' : 'bg-slate-300 dark:bg-slate-600' }}" title="{{ $online ? 'Online' : 'Offline' }}"></span></div><div class="mt-4 flex flex-wrap gap-2"><x-badge>{{ $membership->role->label() }}</x-badge><x-badge :tone="$online ? 'success' : 'slate'">{{ $online ? 'Online' : 'Offline' }}</x-badge></div></div></div>
                        <dl class="mt-5 grid grid-cols-2 gap-3 rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800/60"><div><dt class="text-xs text-slate-500">Current project</dt><dd class="mt-1 truncate font-semibold">{{ $currentProject?->name ?? 'No visible project' }}</dd></div><div><dt class="text-xs text-slate-500">Active tasks</dt><dd class="mt-1 font-semibold">{{ $membership->user->active_tasks_count }}</dd></div></dl><div class="mt-3"><div class="flex justify-between text-[11px] text-slate-500"><span>Workload</span><span>{{ $membership->user->active_tasks_count }} tasks</span></div><div class="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-full rounded-full bg-orbit-500" style="width: {{ round($membership->user->active_tasks_count / $maxWorkload * 100) }}%"></div></div></div>
                        <a href="{{ route('app.workspaces.team.show', [$workspace, $membership]) }}" class="mt-4 inline-flex min-h-10 items-center text-sm font-semibold text-orbit-700 dark:text-orbit-300">View member details →</a>
                        @can('update', $membership)
                            <details class="mt-3 border-t border-slate-100 pt-3 dark:border-slate-800"><summary class="cursor-pointer text-sm font-semibold text-slate-500">Manage access</summary><form method="POST" action="{{ route('internal.workspace-members.update', $membership) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">@csrf @method('PATCH')<x-select name="role" aria-label="Role for {{ $membership->user->name }}">@foreach (App\Enums\WorkspaceRole::assignable() as $value => $label)<option value="{{ $value }}" @selected($membership->role->value === $value)>{{ $label }}</option>@endforeach</x-select><x-select name="status" aria-label="Status for {{ $membership->user->name }}"><option value="active" @selected($membership->status->value === 'active')>Active</option><option value="inactive" @selected($membership->status->value === 'inactive')>Inactive</option></x-select><x-button variant="secondary">Update</x-button></form>@if ($membership->status->value === 'active')<form method="POST" action="{{ route('internal.workspace-members.destroy', $membership) }}" class="mt-2" onsubmit="return confirm('Deactivate this member?')">@csrf @method('DELETE')<button class="text-sm font-semibold text-rose-600 dark:text-rose-400">Deactivate access</button></form>@endif</details>
                        @endcan
                    </article>
                @empty
                    <x-empty-state icon="team" title="No members match" description="Change the search or filters to see other workspace members." class="md:col-span-2" />
                @endforelse
            </div>
        </section>

        <div class="space-y-6">
            @can('invite', $workspace)
                {{-- Heading sits outside the card so it lines up with "Workspace members". --}}
                <div>
                <h2 id="invite-title" class="text-xl font-bold">Invite a teammate</h2>
                <p class="mt-1 text-sm text-slate-500">They receive an email link to join this workspace.</p>
                <section class="mt-4 rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="invite-title"><form method="POST" action="{{ route('internal.invitations.store', $workspace) }}" class="mt-5 space-y-4">@csrf<div><x-label for="email">Email</x-label><x-input id="email" type="email" name="email" value="{{ old('email') }}" required /><x-field-error name="email" /></div><div><x-label for="role">Workspace role</x-label><x-select id="role" name="role">@foreach (App\Enums\WorkspaceRole::assignable() as $value => $label)<option value="{{ $value }}" @selected($value === 'member')>{{ $label }}</option>@endforeach</x-select><x-field-error name="role" /></div><x-button class="w-full">Send invitation</x-button></form></section>
                </div>
            @endcan
            <section class="rounded-3xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="pending-title"><h2 id="pending-title" class="text-lg font-bold">Pending invitations</h2><div class="mt-4 space-y-3">@forelse ($invitations as $invitation)<div class="rounded-xl bg-slate-50 p-3 text-sm dark:bg-slate-800"><p class="truncate font-semibold">{{ $invitation->email }}</p><p class="mt-1 text-xs text-slate-500">{{ $invitation->role->label() }} · expires {{ $invitation->expires_at->diffForHumans() }}</p></div>@empty<p class="text-sm text-slate-500">No pending invitations.</p>@endforelse</div></section>
            @can('transferOwnership', $workspace)
                <section class="rounded-3xl border border-amber-200 bg-amber-50 p-6 dark:border-amber-900/60 dark:bg-amber-950/30" aria-labelledby="ownership-title"><h2 id="ownership-title" class="text-lg font-bold">Transfer ownership</h2><p class="mt-2 text-sm text-slate-600 dark:text-slate-300">Your role becomes admin after transfer.</p><form method="POST" action="{{ route('internal.workspaces.transfer', $workspace) }}" class="mt-4 space-y-3" onsubmit="return confirm('Transfer workspace ownership?')">@csrf<x-select name="member_public_id" required><option value="">Choose active member</option>@foreach ($ownershipCandidates as $candidate)<option value="{{ $candidate->public_id }}">{{ $candidate->user->name }}</option>@endforeach</x-select><x-button variant="secondary" class="w-full">Transfer ownership</x-button></form></section>
            @endcan
        </div>
    </div>
@endsection
