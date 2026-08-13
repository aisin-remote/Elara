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

    @php
        $departmentNames = $departments->pluck('name', 'id');
        $colorPalette = [
            '#2eb0fb', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444', '#ec4899',
            '#14b8a6', '#6366f1', '#84cc16', '#f97316', '#06b6d4', '#a855f7',
            '#e11d48', '#0ea5e9', '#22c55e', '#eab308', '#64748b', '#d946ef',
        ];
        $takenColors = $takenColors->filter()->map(fn ($color) => strtolower($color))->values();
        $defaultColor = collect($colorPalette)->first(
            fn (string $color) => ! $takenColors->contains(strtolower($color))
        ) ?? '#2eb0fb';
        $openCreateModal = $errors->any() && old('_intent') === 'create';
        $openEditModalId = $errors->any() && str_starts_with((string) old('_intent'), 'edit:')
            ? substr((string) old('_intent'), 5)
            : null;
    @endphp

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900" aria-labelledby="systems-title">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-5 dark:border-slate-800 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h3 id="systems-title" class="text-lg font-bold">{{ $systems->total() }} {{ \Illuminate\Support\Str::plural('system', $systems->total()) }}</h3>
                <p class="mt-1 text-xs text-slate-500">Feature requests are raised against these. Each needs a PIC before it can receive one.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <form method="GET" class="flex gap-2">
                    <x-input name="search" value="{{ $search }}" placeholder="Search systems" aria-label="Search systems" class="sm:w-52" />
                    <x-button variant="secondary">Search</x-button>
                </form>
                <x-button type="button" onclick="document.getElementById('add-system-dialog').showModal()">
                    <x-icon name="plus" /> Add system
                </x-button>
            </div>
        </div>

        @if ($systems->isEmpty())
            <div class="p-5"><x-empty-state icon="projects" title="No systems yet" description="Add the systems your team already maintains. Each becomes selectable on the feature request form." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-left text-sm">
                    <thead class="bg-slate-50/80 text-[11px] uppercase tracking-[.1em] text-slate-400 dark:bg-slate-900">
                        <tr>
                            <th class="px-5 py-3">System</th>
                            <th class="px-4 py-3">Plant</th>
                            <th class="px-4 py-3">Department</th>
                            <th class="px-4 py-3">PIC</th>
                            <th class="px-4 py-3">Features</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                        @foreach ($systems as $system)
                            @php($assignments = $system->picAssignments()->filter(fn ($a) => $a->pivot->organization_department_id))
                            <tr class="align-top transition hover:bg-slate-50/70 dark:hover:bg-slate-800/40">
                                <td class="px-5 py-4">
                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="size-3.5 shrink-0 rounded-full" style="background-color: {{ $system->color }}"></span>
                                        <div class="min-w-0">
                                            <p class="truncate font-semibold">{{ $system->name }}</p>
                                            @if ($system->archived_at)
                                                <p class="mt-0.5 text-xs font-semibold text-amber-600 dark:text-amber-400">Archived</p>
                                            @endif
                                        </div>
                                    </div>
                                </td>
                                <td class="whitespace-nowrap px-4 py-4">
                                    @if ($system->plant)
                                        <span class="font-semibold">{{ $system->plant->label() }}</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4">
                                    @forelse ($assignments as $assignment)
                                        <p @class(['mt-1' => ! $loop->first, 'font-medium text-slate-700 dark:text-slate-200'])>
                                            {{ $departmentNames[$assignment->pivot->organization_department_id] ?? $assignment->pivot->organization_department_code ?? 'Department' }}
                                        </p>
                                    @empty
                                        <span class="font-semibold text-amber-600 dark:text-amber-400">No department PIC</span>
                                    @endforelse
                                </td>
                                <td class="px-4 py-4">
                                    @forelse ($assignments as $assignment)
                                        <p @class(['mt-1' => ! $loop->first, 'text-slate-600 dark:text-slate-300'])>{{ $assignment->name }}</p>
                                    @empty
                                        <span class="text-slate-400">—</span>
                                    @endforelse
                                </td>
                                <td class="whitespace-nowrap px-4 py-4 text-slate-500">
                                    {{ $system->active_features_count }} active
                                </td>
                                <td class="px-5 py-4">
                                    <div class="flex justify-end gap-2">
                                        @unless ($system->archived_at)
                                            <x-button type="button" variant="secondary" onclick="document.getElementById('edit-system-{{ $system->public_id }}').showModal()">Edit</x-button>
                                        @endunless
                                        <form method="POST" action="{{ route('internal.master.systems.archive', $system) }}">
                                            @csrf
                                            <x-button variant="secondary">{{ $system->archived_at ? 'Restore' : 'Archive' }}</x-button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <x-pagination :paginator="$systems" class="border-t border-slate-200 p-4 dark:border-slate-800" />
        @endif
    </section>

    <x-modal
        id="add-system-dialog"
        title="Add a system"
        class="w-[min(92vw,720px)] max-h-[90vh] overflow-y-auto"
        x-data
        x-init="@js($openCreateModal) && $el.showModal()"
    >
        <p class="mb-4 text-xs text-slate-500">It gets its own board, statuses, and task list, exactly like a project.</p>
        <form method="POST" action="{{ route('internal.master.systems.store', $workspace) }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_intent" value="create">
            <div><x-label for="new-name">Name</x-label><x-input id="new-name" name="name" value="{{ old('name') }}" required /><x-field-error name="name" /></div>
            <div>
                <x-label for="new-plant">Plant</x-label>
                <x-select id="new-plant" name="plant" required>
                    <option value="" disabled @selected(! old('plant'))>Choose a plant</option>
                    @foreach (\App\Enums\SystemPlant::cases() as $plant)
                        <option value="{{ $plant->value }}" @selected(old('plant') === $plant->value)>{{ $plant->label() }}</option>
                    @endforeach
                </x-select>
                <x-field-error name="plant" />
            </div>
            @php($picRows = max(1, count(old('pics', [[]]))))
            <div x-data="{ rows: {{ $picRows }} }">
                <x-label>PIC per department</x-label>
                @if ($departments->isEmpty())
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
                    @for ($row = 0; $row < 5; $row++)
                        <div class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]" @if ($row > 0) x-cloak x-show="rows > {{ $row }}" @endif>
                            <div class="min-w-0">
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
                            <div class="min-w-0">
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
            <div
                x-data="systemColorPicker({
                    color: @js(old('color', $defaultColor)),
                    taken: @js($takenColors),
                    palette: @js($colorPalette),
                })"
            >
                <x-label for="new-color">Colour</x-label>
                <div class="mt-1 flex items-center gap-2">
                    <x-input id="new-color" type="color" name="color" x-model="color" class="h-11 w-16 p-1" />
                    <x-button type="button" variant="secondary" x-on:click="randomize()" aria-label="Randomise colour">
                        <x-icon name="refresh" /> Random
                    </x-button>
                </div>
                <p class="mt-1 text-xs text-slate-500">Each system gets its own colour so markers stay distinct.</p>
                <x-field-error name="color" />
            </div>
            <div><x-label for="new-description">Description</x-label><x-textarea id="new-description" name="description" rows="3">{{ old('description') }}</x-textarea><x-field-error name="description" /></div>
            <div class="flex justify-end gap-3">
                <x-button type="button" variant="secondary" onclick="this.closest('dialog').close()">Cancel</x-button>
                <x-button>Add system</x-button>
            </div>
        </form>
    </x-modal>

    @foreach ($systems as $system)
        @unless ($system->archived_at)
            @php($assignments = $system->picAssignments()->filter(fn ($a) => $a->pivot->organization_department_id))
            <x-modal
                id="edit-system-{{ $system->public_id }}"
                title="Edit {{ $system->name }}"
                class="w-[min(92vw,720px)] max-h-[90vh] overflow-y-auto"
                x-data
                x-init="@js($openEditModalId === $system->public_id) && $el.showModal()"
            >
                <div class="space-y-4">
                    <form method="POST" action="{{ route('internal.master.systems.update', $system) }}" class="space-y-4">
                        @csrf @method('PATCH')
                        <input type="hidden" name="_intent" value="edit:{{ $system->public_id }}">
                        @php($editingThis = $openEditModalId === $system->public_id)
                        <div class="grid gap-3 sm:grid-cols-[1fr_140px]">
                            <div><x-label for="name-{{ $system->public_id }}">Name</x-label><x-input id="name-{{ $system->public_id }}" name="name" value="{{ $editingThis ? old('name', $system->name) : $system->name }}" required /></div>
                            <div>
                                <x-label for="plant-{{ $system->public_id }}">Plant</x-label>
                                <x-select id="plant-{{ $system->public_id }}" name="plant" required>
                                    @foreach (\App\Enums\SystemPlant::cases() as $plant)
                                        <option value="{{ $plant->value }}" @selected(($editingThis ? old('plant', $system->plant?->value) : $system->plant?->value) === $plant->value)>{{ $plant->label() }}</option>
                                    @endforeach
                                </x-select>
                            </div>
                        </div>
                        <div
                            x-data="systemColorPicker({
                                color: @js($editingThis ? old('color', $system->color) : $system->color),
                                taken: @js($takenColors),
                                allow: @js(strtolower($system->color)),
                                palette: @js($colorPalette),
                            })"
                        >
                            <x-label for="color-{{ $system->public_id }}">Colour</x-label>
                            <div class="mt-1 flex items-center gap-2">
                                <x-input id="color-{{ $system->public_id }}" type="color" name="color" x-model="color" class="h-11 w-16 p-1" />
                                <x-button type="button" variant="secondary" x-on:click="randomize()" aria-label="Randomise colour">
                                    <x-icon name="refresh" /> Random
                                </x-button>
                            </div>
                            <x-field-error name="color" />
                        </div>
                        <div><x-label for="description-{{ $system->public_id }}">Description</x-label><x-textarea id="description-{{ $system->public_id }}" name="description" rows="3">{{ $editingThis ? old('description', $system->description) : $system->description }}</x-textarea></div>
                        {{-- Inline form only: a block-form php directive here would pair with the
                             inline one further up the file and swallow everything between them. --}}
                        @php($existingPics = $assignments->map(fn ($a) => ['department' => $a->pivot->organization_department_id, 'pic' => $a->public_id])->values())
                        {{-- old() belongs to the system whose save was rejected; every other modal
                             on the page keeps showing what the database holds. --}}
                        @php($picValue = fn (string $key, $default) => $editingThis ? old($key, $default) : $default)
                        @php($picRows = max(1, $editingThis && is_array(old('pics')) ? count(old('pics')) : $existingPics->count()))

                        <div x-data="{ rows: {{ $picRows }} }">
                            <x-label>PIC per department</x-label>

                            @if ($editingThis)
                                <div class="mt-2"><x-form-errors :except="['name', 'color', 'plant', 'description']" /></div>
                            @endif

                            @if ($departments->isEmpty())
                                <p class="mt-1 rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 dark:bg-amber-950/40 dark:text-amber-200">
                                    The organisation directory is unreachable, so departments cannot be listed. Existing PICs are unaffected.
                                </p>
                            @else
                                <p class="mt-1 text-xs text-slate-500">Feature requests reach the PIC of the department they come from. Set a row back to “No PIC” to drop it — everything saves with the button below.</p>
                                @for ($row = 0; $row < 5; $row++)
                                    <div class="mt-2 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]" @if ($row > 0) x-cloak x-show="rows > {{ $row }}" @endif>
                                        <div class="min-w-0">
                                            <x-searchable-select
                                                :id="'pic-department-'.$system->public_id.'-'.$row"
                                                name="pics[{{ $row }}][organization_department_id]"
                                                :selected="$picValue('pics.'.$row.'.organization_department_id', data_get($existingPics->get($row), 'department'))"
                                                empty-label="No department"
                                                search-placeholder="Search departments…"
                                                :options="$departments->map(fn ($d) => [
                                                    'value' => $d->id,
                                                    'label' => $d->name.($d->code ? ' ('.$d->code.')' : ''),
                                                ])" />
                                            <x-field-error name="pics.{{ $row }}.organization_department_id" />
                                        </div>
                                        <div class="min-w-0">
                                            <x-searchable-select
                                                :id="'pic-person-'.$system->public_id.'-'.$row"
                                                name="pics[{{ $row }}][pic_public_id]"
                                                :selected="$picValue('pics.'.$row.'.pic_public_id', data_get($existingPics->get($row), 'pic'))"
                                                empty-label="No PIC"
                                                placeholder="Choose a PIC"
                                                search-placeholder="Search people…"
                                                :options="$candidates->map(fn ($c) => ['value' => $c->user->public_id, 'label' => $c->user->name])->values()" />
                                            <x-field-error name="pics.{{ $row }}.pic_public_id" />
                                        </div>
                                    </div>
                                @endfor
                                <x-button type="button" variant="secondary" class="mt-2 w-full" x-show="rows < 5" x-on:click="rows++">Add PIC</x-button>
                            @endif

                            <x-field-error name="pics" />
                        </div>

                        <div class="flex justify-end gap-3">
                            <x-button type="button" variant="secondary" onclick="this.closest('dialog').close()">Cancel</x-button>
                            <x-button>Save system</x-button>
                        </div>
                    </form>
                </div>
            </x-modal>
        @endunless
    @endforeach

    <script>
        function systemColorPicker({ color, taken = [], allow = null, palette = [] }) {
            return {
                color,
                taken: taken.map((value) => String(value).toLowerCase()),
                allow: allow ? String(allow).toLowerCase() : null,
                palette,
                blocked() {
                    return this.taken.filter((value) => value !== this.allow);
                },
                randomize() {
                    const blocked = this.blocked();
                    const free = this.palette.filter((value) => ! blocked.includes(String(value).toLowerCase()));

                    if (free.length) {
                        this.color = free[Math.floor(Math.random() * free.length)];
                        return;
                    }

                    for (let attempt = 0; attempt < 80; attempt++) {
                        const hex = `#${Math.floor(Math.random() * 0xffffff).toString(16).padStart(6, '0')}`;
                        if (! blocked.includes(hex.toLowerCase())) {
                            this.color = hex;
                            return;
                        }
                    }
                },
            };
        }
    </script>
@endsection
