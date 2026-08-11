@extends('layouts.app')

@section('title', 'Systems')
@section('page-title', 'Settings')
@section('master-title', 'Systems')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    <div class="grid gap-6 xl:grid-cols-[1fr_380px] xl:items-start">
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="systems-title">
            <div class="flex flex-col gap-3 border-b border-slate-200 p-5 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h3 id="systems-title" class="text-lg font-bold">{{ $systems->count() }} {{ \Illuminate\Support\Str::plural('system', $systems->count()) }}</h3>
                    <p class="mt-1 text-xs text-slate-500">Feature requests are raised against these. Each needs a PIC before it can receive one.</p>
                </div>
                <form method="GET" class="flex gap-2">
                    <x-input name="search" value="{{ $search }}" placeholder="Search systems" aria-label="Search systems" class="sm:w-52" />
                    <x-button variant="secondary">Search</x-button>
                </form>
            </div>

            @forelse ($systems as $system)
                @php($pic = $system->pic())
                <div class="border-b border-slate-100 p-4 last:border-0 dark:border-slate-800" x-data="{ editing: false }">
                    <div class="flex flex-wrap items-center gap-3" x-show="! editing">
                        <span class="size-4 shrink-0 rounded-full" style="background-color: {{ $system->color }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $system->name }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                PIC {{ $pic?->name ?? 'not set' }}
                                @if ($system->organization_department_code)
                                    · {{ $system->organization_department_code }}
                                @endif
                                · {{ $system->active_features_count }} active {{ \Illuminate\Support\Str::plural('feature', $system->active_features_count) }}
                                @if ($system->archived_at)
                                    · <span class="font-semibold text-amber-600 dark:text-amber-400">Archived</span>
                                @endif
                            </p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            @unless ($system->archived_at)
                                <x-button type="button" variant="secondary" x-on:click="editing = true">Edit</x-button>
                            @endunless
                            <form method="POST" action="{{ route('internal.master.systems.archive', $system) }}">
                                @csrf
                                <x-button variant="secondary">{{ $system->archived_at ? 'Restore' : 'Archive' }}</x-button>
                            </form>
                        </div>
                    </div>

                    <form x-cloak x-show="editing" method="POST" action="{{ route('internal.master.systems.update', $system) }}" class="space-y-3">
                        @csrf @method('PATCH')
                        {{-- Department before PIC, matching the add form: you pick the owning
                             department first, then the person inside it. --}}
                        <div class="grid gap-3 sm:grid-cols-[1fr_1fr_1fr_120px]">
                            <div><x-label for="name-{{ $system->public_id }}">Name</x-label><x-input id="name-{{ $system->public_id }}" name="name" value="{{ $system->name }}" required /></div>
                            @if ($departments->isNotEmpty())
                                <div>
                                    <x-label for="department-{{ $system->public_id }}">Department</x-label>
                                    <x-searchable-select
                                        id="department-{{ $system->public_id }}"
                                        name="organization_department_id"
                                        :selected="$system->organization_department_id"
                                        empty-label="No department"
                                        search-placeholder="Search departments…"
                                        :options="$departments->map(fn ($d) => [
                                            'value' => $d->id,
                                            'label' => $d->name.($d->code ? ' ('.$d->code.')' : ''),
                                        ])" />
                                </div>
                            @else
                                {{-- Preserve what is stored rather than blanking it: with the picker
                                     unavailable, submitting the form must not silently clear the
                                     department someone set while it was up. --}}
                                <input type="hidden" name="organization_department_id" value="{{ $system->organization_department_id }}">
                            @endif
                            <div>
                                <x-label for="pic-{{ $system->public_id }}">PIC</x-label>
                                <x-searchable-select
                                    id="pic-{{ $system->public_id }}"
                                    name="pic_public_id"
                                    :selected="$pic?->public_id"
                                    placeholder="Choose a PIC"
                                    search-placeholder="Search people…"
                                    :options="$candidates->map(fn ($c) => ['value' => $c->user->public_id, 'label' => $c->user->name])->values()" />
                            </div>
                            <div><x-label for="color-{{ $system->public_id }}">Colour</x-label><x-input id="color-{{ $system->public_id }}" type="color" name="color" value="{{ $system->color }}" /></div>
                        </div>
                        <div><x-label for="description-{{ $system->public_id }}">Description</x-label><x-textarea id="description-{{ $system->public_id }}" name="description" rows="3">{{ $system->description }}</x-textarea></div>
                        <div class="flex gap-3"><x-button>Save system</x-button><x-button type="button" variant="secondary" x-on:click="editing = false">Cancel</x-button></div>
                    </form>
                </div>
            @empty
                <div class="p-5"><x-empty-state icon="projects" title="No systems yet" description="Add the systems your team already maintains. Each becomes selectable on the feature request form." /></div>
            @endforelse
        </section>

        <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900" aria-labelledby="new-system-title">
            <h3 id="new-system-title" class="text-lg font-bold">Add a system</h3>
            <p class="mt-1 text-xs text-slate-500">It gets its own board, statuses, and task list, exactly like a project.</p>
            <form method="POST" action="{{ route('internal.master.systems.store', $workspace) }}" class="mt-4 space-y-4">
                @csrf
                <div><x-label for="new-name">Name</x-label><x-input id="new-name" name="name" required /><x-field-error name="name" /></div>
                <div>
                    <x-label for="new-department">Department</x-label>
                    @if ($departments->isEmpty())
                        {{-- Not an empty picker: an empty list here means the directory could
                             not be reached, which is a different thing from having none. --}}
                        <p class="mt-1 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            The organisation directory is unreachable, so departments cannot be listed.
                            You can still add the system and set its department later.
                        </p>
                    @else
                        <x-searchable-select
                            id="new-department"
                            name="organization_department_id"
                            :selected="old('organization_department_id')"
                            empty-label="No department"
                            search-placeholder="Search departments…"
                            :options="$departments->map(fn ($d) => [
                                'value' => $d->id,
                                'label' => $d->name.($d->code ? ' ('.$d->code.')' : ''),
                            ])" />
                        <p class="mt-1 text-xs text-slate-500">Which department owns this system. Read from the organisation directory.</p>
                    @endif
                    <x-field-error name="organization_department_id" />
                </div>
                <div>
                    <x-label for="new-pic">PIC</x-label>
                    <x-searchable-select
                        id="new-pic"
                        name="pic_public_id"
                        :selected="old('pic_public_id')"
                        placeholder="Choose the person who knows it best"
                        search-placeholder="Search people…"
                        :options="$candidates->map(fn ($c) => ['value' => $c->user->public_id, 'label' => $c->user->name])->values()" />
                    <x-field-error name="pic_public_id" />
                </div>
                <div><x-label for="new-color">Colour</x-label><x-input id="new-color" type="color" name="color" value="#2eb0fb" /><x-field-error name="color" /></div>
                <div><x-label for="new-description">Description</x-label><x-textarea id="new-description" name="description" rows="3"></x-textarea><x-field-error name="description" /></div>
                <x-button class="w-full">Add system</x-button>
            </form>
        </section>
    </div>
@endsection
