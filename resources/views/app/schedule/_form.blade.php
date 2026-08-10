<div><x-label for="{{ $prefix }}-event-title">Title</x-label><x-input id="{{ $prefix }}-event-title" name="title" required data-schedule-field="title"/><x-field-error name="title"/></div>
<div><x-label for="{{ $prefix }}-event-description">Description</x-label><x-textarea id="{{ $prefix }}-event-description" name="description" rows="3" data-schedule-field="description"/><x-field-error name="description"/></div>
<div class="grid gap-4 sm:grid-cols-2">
    <div><x-label for="{{ $prefix }}-event-start">Start</x-label><x-input id="{{ $prefix }}-event-start" type="datetime-local" name="start_at" required data-schedule-field="start_at"/><x-field-error name="start_at"/></div>
    <div><x-label for="{{ $prefix }}-event-end">End</x-label><x-input id="{{ $prefix }}-event-end" type="datetime-local" name="end_at" required data-schedule-field="end_at"/><x-field-error name="end_at"/></div>
</div>
<div class="grid gap-4 sm:grid-cols-2">
    <div><x-label for="{{ $prefix }}-event-project">Project</x-label><x-select id="{{ $prefix }}-event-project" name="project_public_id" data-schedule-field="project_public_id"><option value="">No project</option>@foreach($projects as $project)<option value="{{ $project->public_id }}">{{ $project->name }}</option>@endforeach</x-select></div>
    <div><x-label for="{{ $prefix }}-event-color">Color</x-label><x-input id="{{ $prefix }}-event-color" type="color" name="color" value="#6366f1" data-schedule-field="color"/></div>
</div>
<div><x-label for="{{ $prefix }}-event-url">Meeting link</x-label><x-input id="{{ $prefix }}-event-url" type="url" name="meeting_url" placeholder="https://meet.example.com/…" data-schedule-field="meeting_url"/><x-field-error name="meeting_url"/></div>
<fieldset><legend class="text-sm font-semibold text-slate-700 dark:text-slate-300">Attendees</legend><div class="mt-2 grid max-h-36 gap-2 overflow-y-auto rounded-xl border border-slate-200 p-3 sm:grid-cols-2 dark:border-slate-700">@foreach($members as $membership)<label class="flex items-center gap-2 text-sm"><input type="checkbox" name="attendee_public_ids[]" value="{{ $membership->user->public_id }}" data-schedule-attendee class="rounded border-slate-300 text-orbit-600"><span>{{ $membership->user->name }}</span></label>@endforeach</div></fieldset>
