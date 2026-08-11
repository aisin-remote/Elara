@extends('layouts.app')

@section('title', 'Systems')
@section('page-title', 'Settings')
@section('master-title', 'Systems')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    {{-- Removing a PIC is refused on a key no field here owns. Without an outlet the button
         would look broken instead of refused. --}}
    <x-form-errors class="mb-4" />

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

            @php($departmentNames = $departments->pluck('name', 'id'))
            @forelse ($systems as $system)
                @php($assignments = $system->picAssignments())
                <div class="border-b border-slate-100 p-4 last:border-0 dark:border-slate-800" x-data="{ editing: false }">
                    <div class="flex flex-wrap items-center gap-3" x-show="! editing">
                        <span class="size-4 shrink-0 rounded-full" style="background-color: {{ $system->color }}"></span>
                        <div class="min-w-0 flex-1">
                            <p class="truncate font-semibold">{{ $system->name }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                @forelse ($assignments->filter(fn ($a) => $a->pivot->organization_department_id) as $assignment)
                                    <span class="font-medium text-slate-600 dark:text-slate-300">{{ $departmentNames[$assignment->pivot->organization_department_id] ?? $assignment->pivot->organization_department_code ?? 'Department' }}</span>
                                    {{ $assignment->name }} ·
                                @empty
                                    <span class="font-semibold text-amber-600 dark:text-amber-400">No department PIC</span> ·
                                @endforelse
                                {{ $system->active_features_count }} active {{ \Illuminate\Support\Str::plural('feature', $system->active_features_count) }}
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

                    {{-- The PIC forms are siblings of the detail form, never nested inside it:
                         a form within a form is not valid HTML and the inner one is dropped. --}}
                    <div x-cloak x-show="editing" class="space-y-4">
                        <form method="POST" action="{{ route('internal.master.systems.update', $system) }}" class="space-y-3">
                            @csrf @method('PATCH')
                            <div class="grid gap-3 sm:grid-cols-[1fr_120px]">
                                <div><x-label for="name-{{ $system->public_id }}">Name</x-label><x-input id="name-{{ $system->public_id }}" name="name" value="{{ $system->name }}" required /></div>
                                <div><x-label for="color-{{ $system->public_id }}">Colour</x-label><x-input id="color-{{ $system->public_id }}" type="color" name="color" value="{{ $system->color }}" /></div>
                            </div>
                            <div><x-label for="description-{{ $system->public_id }}">Description</x-label><x-textarea id="description-{{ $system->public_id }}" name="description" rows="3">{{ $system->description }}</x-textarea></div>
                            <div class="flex gap-3"><x-button>Save system</x-button><x-button type="button" variant="secondary" x-on:click="editing = false">Cancel</x-button></div>
                        </form>

                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                            <h4 class="text-sm font-bold">PIC per department</h4>
                            <p class="mt-1 text-xs text-slate-500">One system can serve several departments, each answering to its own person. Feature requests reach the PIC of the department they come from.</p>

                            <div class="mt-3 space-y-2">
                                @forelse ($assignments->filter(fn ($a) => $a->pivot->organization_department_id) as $assignment)
                                    <div class="flex flex-wrap items-center gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm dark:bg-slate-800">
                                        <span class="font-semibold">{{ $departmentNames[$assignment->pivot->organization_department_id] ?? $assignment->pivot->organization_department_code ?? 'Department' }}</span>
                                        <span class="min-w-0 flex-1 truncate text-slate-500">{{ $assignment->name }}</span>
                                        <form method="POST" action="{{ route('internal.master.systems.pics.remove', $system) }}" onsubmit="return confirm('Remove this PIC?')">
                                            @csrf @method('DELETE')
                                            <input type="hidden" name="organization_department_id" value="{{ $assignment->pivot->organization_department_id }}">
                                            <x-button variant="secondary">Remove</x-button>
                                        </form>
                                    </div>
                                @empty
                                    <p class="text-sm text-slate-500">No department has a PIC yet.</p>
                                @endforelse
                            </div>

                            @if ($departments->isEmpty())
                                <p class="mt-3 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                    The organisation directory is unreachable, so departments cannot be listed. Existing PICs are unaffected.
                                </p>
                            @else
                                <form method="POST" action="{{ route('internal.master.systems.pics.assign', $system) }}" class="mt-3 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                                    @csrf
                                    <div>
                                        <x-label for="pic-department-{{ $system->public_id }}">Department</x-label>
                                        <x-searchable-select
                                            id="pic-department-{{ $system->public_id }}"
                                            name="organization_department_id"
                                            placeholder="Choose a department"
                                            search-placeholder="Search departments…"
                                            :options="$departments->map(fn ($d) => [
                                                'value' => $d->id,
                                                'label' => $d->name.($d->code ? ' ('.$d->code.')' : ''),
                                            ])" />
                                    </div>
                                    <div>
                                        <x-label for="pic-person-{{ $system->public_id }}">PIC</x-label>
                                        <x-searchable-select
                                            id="pic-person-{{ $system->public_id }}"
                                            name="pic_public_id"
                                            placeholder="Choose a PIC"
                                            search-placeholder="Search people…"
                                            :options="$candidates->map(fn ($c) => ['value' => $c->user->public_id, 'label' => $c->user->name])->values()" />
                                    </div>
                                    <x-button>Add PIC</x-button>
                                </form>
                            @endif
                        </div>
                    </div>
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
                @php($picRows = max(1, count(old('pics', [[]]))))
                <div x-data="{ rows: {{ $picRows }} }">
                    <x-label>PIC per department</x-label>
                    @if ($departments->isEmpty())
                        {{-- Not an empty picker: an empty list here means the directory could
                             not be reached, which is a different thing from having none. --}}
                        <p class="mt-1 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                            The organisation directory is unreachable, so departments cannot be listed.
                            You can still add the system with one PIC and name the rest later.
                        </p>
                        <div class="mt-2">
                            <x-searchable-select
                                id="new-pic"
                                name="pics[0][pic_public_id]"
                                :selected="old('pics.0.pic_public_id')"
                                placeholder="Choose the person who knows it best"
                                search-placeholder="Search people…"
                                :options="$candidates->map(fn ($c) => ['value' => $c->user->public_id, 'label' => $c->user->name])->values()" />
                        </div>
                    @else
                        <p class="mt-1 text-xs text-slate-500">A system can serve several departments, each with its own PIC. Add them all here — no need to save first.</p>
                        {{-- The rows are rendered up front and revealed one at a time. The picker
                             is a server-rendered component, so cloning it in the browser would
                             mean rebuilding it in JavaScript for no gain at five rows. --}}
                        @for ($row = 0; $row < 5; $row++)
                            <div class="mt-2 grid gap-2 sm:grid-cols-2" @if ($row > 0) x-cloak x-show="rows > {{ $row }}" @endif>
                                <div>
                                    <x-searchable-select
                                        id="new-pic-department-{{ $row }}"
                                        name="pics[{{ $row }}][organization_department_id]"
                                        :selected="old('pics.'.$row.'.organization_department_id')"
                                        empty-label="No department"
                                        search-placeholder="Search departments…"
                                        :options="$departments->map(fn ($d) => [
                                            'value' => $d->id,
                                            'label' => $d->name.($d->code ? ' ('.$d->code.')' : ''),
                                        ])" />
                                    <x-field-error name="pics.{{ $row }}.organization_department_id" />
                                </div>
                                <div>
                                    <x-searchable-select
                                        id="new-pic-{{ $row }}"
                                        name="pics[{{ $row }}][pic_public_id]"
                                        :selected="old('pics.'.$row.'.pic_public_id')"
                                        placeholder="Choose a PIC"
                                        search-placeholder="Search people…"
                                        :options="$candidates->map(fn ($c) => ['value' => $c->user->public_id, 'label' => $c->user->name])->values()" />
                                    <x-field-error name="pics.{{ $row }}.pic_public_id" />
                                </div>
                            </div>
                        @endfor
                        <x-button type="button" variant="secondary" class="mt-2 w-full" x-show="rows < 5" x-on:click="rows++">Add another department</x-button>
                    @endif
                    <x-field-error name="pics" />
                </div>
                <div><x-label for="new-color">Colour</x-label><x-input id="new-color" type="color" name="color" value="#2eb0fb" /><x-field-error name="color" /></div>
                <div><x-label for="new-description">Description</x-label><x-textarea id="new-description" name="description" rows="3"></x-textarea><x-field-error name="description" /></div>
                <x-button class="w-full">Add system</x-button>
            </form>
        </section>
    </div>
@endsection
