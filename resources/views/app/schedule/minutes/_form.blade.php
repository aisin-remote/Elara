@php
    $editing = $meetingMinute !== null;
    $formAction = $formAction ?? ($editing ? route('internal.meeting-minutes.update', $meetingMinute) : route('internal.meeting-minutes.store', $workspace));
    $formMethod = $formMethod ?? ($editing ? 'PATCH' : 'POST');
    $cancelUrl = $cancelUrl ?? ($editing ? route('app.schedule.minutes.show', [$workspace, $meetingMinute]) : route('app.schedule.minutes.index', $workspace));
    $submitLabel = $submitLabel ?? ($editing ? 'Save changes' : 'Create MOM');
    $titleValue = old('title', $meetingMinute?->title ?? ($scheduleEvent ? 'MOM – '.$scheduleEvent->title : ''));
    $meetingAtValue = old('meeting_at', $meetingMinute?->meeting_at?->format('Y-m-d\TH:i') ?? $scheduleEvent?->start_at?->timezone($workspace->timezone)->format('Y-m-d\TH:i'));
    $summaryValue = old('summary', $meetingMinute?->summary ?? '');
    $scheduleEventPublicId = old('schedule_event_public_id', $meetingMinute?->scheduleEvent?->public_id ?? $scheduleEvent?->public_id);
    $projectPublicId = old('project_public_id', $meetingMinute?->project?->public_id ?? $scheduleEvent?->project?->public_id);
    $memberOptions = $picUsers->map(fn ($user) => [
        'value' => $user->public_id,
        'label' => $user->name,
        'subtitle' => $user->job_title ?: $user->email,
    ])->values();
    $formState = [
        'initialItems' => $formItems,
        'users' => $memberOptions,
        'aiUrl' => $aiSummaryUrl,
        'initialSummary' => $summaryValue,
    ];
@endphp

<form method="POST" action="{{ $formAction }}" enctype="multipart/form-data" class="min-w-0 max-w-full space-y-6"
    x-data="meetingMinuteForm({{ Js::from($formState) }})">
    @csrf
    @if ($formMethod !== 'POST') @method($formMethod) @endif
    <input type="hidden" name="schedule_event_public_id" value="{{ $scheduleEventPublicId }}">

    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900 dark:bg-rose-950/50 dark:text-rose-300">
            <p class="font-bold">Please review the highlighted information.</p>
            <ul class="mt-2 list-disc space-y-1 pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <section class="min-w-0 max-w-full overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <div><h3 class="text-lg font-bold">Meeting details</h3><p class="mt-1 text-sm text-slate-500">Record the meeting once, then manage every follow-up in the table below.</p></div>
            @if ($scheduleEvent)<span class="rounded-full bg-sky-100 px-3 py-1 text-xs font-bold text-sky-700 dark:bg-sky-950 dark:text-sky-300">Linked to Schedule</span>@endif
        </div>

        <div class="grid gap-5 p-5 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div><x-label for="title">Meeting title</x-label><x-input id="title" name="title" value="{{ $titleValue }}" maxlength="200" required placeholder="e.g. Cubic Pro scope alignment" /><x-field-error name="title" /></div>
            <div><x-label for="meeting_at">Date and time</x-label><x-input id="meeting_at" type="datetime-local" name="meeting_at" value="{{ $meetingAtValue }}" required /><x-field-error name="meeting_at" /></div>

            <div class="lg:col-span-2">
                <x-label for="project_public_id">Related project / system <span class="font-normal text-slate-400">(optional)</span></x-label>
                <x-select id="project_public_id" name="project_public_id">
                    <option value="">General / no project</option>
                    @foreach ($projects as $project)
                        <option value="{{ $project->public_id }}" @selected($projectPublicId === $project->public_id)>{{ $project->type->label() }} · {{ $project->name }}</option>
                    @endforeach
                </x-select>
                <x-field-error name="project_public_id" />
            </div>

            <div class="lg:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <x-label for="summary">Summary <span class="font-normal text-slate-400">(optional)</span></x-label>
                    <button type="button" x-on:click="generateSummary()" :disabled="generatingSummary" class="inline-flex items-center gap-2 rounded-lg bg-violet-100 px-3 py-2 text-xs font-bold text-violet-700 transition hover:bg-violet-200 disabled:opacity-50 dark:bg-violet-950 dark:text-violet-300 dark:hover:bg-violet-900">
                        <x-icon name="sparkles" /><span x-text="generatingSummary ? 'Generating…' : 'Generate from action items'"></span>
                    </button>
                </div>
                <x-textarea id="summary" name="summary" rows="4" maxlength="20000" x-model="summary" placeholder="Key decisions, context, and unresolved points."></x-textarea>
                <p x-show="summaryError" x-cloak x-text="summaryError" class="mt-2 text-sm text-rose-600"></p>
                <x-field-error name="summary" />
            </div>
        </div>
    </section>

    <section class="w-full min-w-0 max-w-full rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4 dark:border-slate-800">
            <div><h3 class="text-lg font-bold">Action items</h3><p class="mt-1 text-sm text-slate-500">Edit directly in the table. An empty due date is saved as TBA.</p></div>
            <x-button type="button" variant="secondary" x-on:click="addItem()"><x-icon name="plus" />Add row</x-button>
        </div>

        <div class="w-full min-w-0 max-w-full overflow-x-auto overscroll-x-contain rounded-b-2xl">
            <table class="w-full min-w-[760px] table-fixed text-left text-sm lg:min-w-0">
                <colgroup>
                    <col class="w-[36%]">
                    <col class="w-[28%]">
                    <col class="w-[15%]">
                    <col class="w-[15%]">
                    <col class="w-[6%]">
                </colgroup>
                <thead class="bg-slate-50 text-xs font-bold uppercase tracking-wide text-slate-500 dark:bg-slate-950/60">
                    <tr><th class="px-4 py-3">Action / decision</th><th class="px-4 py-3">PIC</th><th class="px-4 py-3">Due date</th><th class="px-4 py-3">Status</th><th class="px-2 py-3"><span class="sr-only">Remove</span></th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    <template x-for="(item, index) in items" :key="item.key">
                        <tr class="align-top">
                            <td class="min-w-0 p-3"><textarea x-model="item.content" :name="`items[${index}][content]`" rows="2" maxlength="5000" required class="block w-full min-w-0 resize-y rounded-lg border-slate-300 bg-white text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-950" placeholder="What was decided or needs follow-up?"></textarea></td>
                            <td class="min-w-0 p-3">
                                <div class="relative" x-on:keydown.escape.stop="item.picOpen = false">
                                    <input type="hidden" :name="`items[${index}][pic_user_public_id]`" x-model="item.pic_user_public_id">
                                    <input x-model="item.pic_name" :name="`items[${index}][pic_name]`" maxlength="120" required autocomplete="off" x-on:focus="openPic(item, $event.currentTarget)" x-on:click.stop="openPic(item, $event.currentTarget)" x-on:input="editPic(item, $event.currentTarget)" class="block min-w-0 w-full rounded-lg border-slate-300 bg-white text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-950" placeholder="Search user or type a name">
                                    <template x-teleport="body">
                                        <div x-show="item.picOpen" x-cloak x-on:click.outside="item.picOpen = false" :style="item.picMenuStyle" class="fixed z-[100] overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl dark:border-slate-700 dark:bg-slate-900">
                                            <div class="max-h-52 overflow-y-auto py-1">
                                                <template x-for="user in matchingUsers(item.pic_name)" :key="user.value">
                                                    <button type="button" x-on:click="choosePic(item, user)" class="block w-full px-3 py-2 text-left hover:bg-slate-50 dark:hover:bg-slate-800"><strong class="block truncate text-sm" x-text="user.label"></strong><small class="block truncate text-slate-500" x-text="user.subtitle"></small></button>
                                                </template>
                                                <p x-show="matchingUsers(item.pic_name).length === 0" class="px-3 py-3 text-xs text-slate-500">No registered user matches.</p>
                                            </div>
                                            <button type="button" x-show="item.pic_name.trim() !== ''" x-on:click="useFreeText(item)" class="flex w-full items-center gap-2 border-t border-slate-100 px-3 py-2 text-left text-xs font-bold text-orbit-700 dark:border-slate-800 dark:text-orbit-300">Use “<span class="max-w-44 truncate" x-text="item.pic_name"></span>” as free text</button>
                                        </div>
                                    </template>
                                </div>
                            </td>
                            <td class="min-w-0 p-3"><input type="date" x-model="item.due_date" :name="`items[${index}][due_date]`" class="block w-full min-w-0 rounded-lg border-slate-300 bg-white text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-950"></td>
                            <td class="min-w-0 p-3"><select x-model="item.status" :name="`items[${index}][status]`" required class="block w-full min-w-0 rounded-lg border-slate-300 bg-white text-sm focus:border-orbit-500 focus:ring-orbit-500 dark:border-slate-700 dark:bg-slate-950">@foreach($statuses as $status)<option value="{{ $status->value }}">{{ $status->label() }}</option>@endforeach</select></td>
                            <td class="px-2 py-3 text-center"><button type="button" x-on:click="removeItem(index)" :disabled="items.length === 1" class="rounded-lg p-2 text-rose-600 transition hover:bg-rose-50 disabled:cursor-not-allowed disabled:opacity-30 dark:hover:bg-rose-950/50" aria-label="Remove action item"><x-icon name="trash" /></button></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    <section class="rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(320px,520px)] lg:items-center"><div><h3 class="text-lg font-bold">Documents</h3><p class="mt-1 text-sm text-slate-500">Attach meeting notes, presentations, spreadsheets, images, or a zip archive.</p></div><input type="file" name="attachments[]" multiple class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-slate-100 file:px-4 file:py-2.5 file:font-bold file:text-slate-700 hover:file:bg-slate-200 dark:file:bg-slate-800 dark:file:text-slate-200" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip"></div>
    </section>

    <div class="flex flex-wrap justify-end gap-3"><x-link-button href="{{ $cancelUrl }}" variant="secondary">Cancel</x-link-button><x-button>{{ $submitLabel }}</x-button></div>
</form>
