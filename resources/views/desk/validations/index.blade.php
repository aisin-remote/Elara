@extends('layouts.requester')

@section('title', 'Validations')
@section('page-title', 'Waiting on me')

@section('content')
    <div class="pb-6">
        <h2 class="text-2xl font-bold tracking-tight">{{ $open->count() + $momActionItems['total'] }} waiting on you</h2>
        <p class="mt-2 text-sm text-slate-500">
            Anything ITD is blocked on: a question they asked about your request, or a finished piece of work
            waiting for your confirmation.
        </p>
    </div>

    @if ($momActionItems['items'] !== [])
        <section class="mb-6 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h2 class="text-lg font-bold">MOM follow-ups</h2><p class="mt-1 text-sm text-slate-500">Update meeting action items assigned to you.</p></div>
            <div class="divide-y divide-slate-100 dark:divide-slate-800">@foreach($momActionItems['items'] as $item)<div class="flex flex-wrap items-center gap-3 p-4"><a href="{{ $item['url'] }}" class="min-w-0 flex-1"><strong class="block truncate text-sm hover:text-orbit-700 dark:hover:text-orbit-300">{{ $item['content'] }}</strong><span class="mt-1 block truncate text-xs text-slate-500">{{ $item['minute'] }} · due {{ $item['due'] }}</span></a><form method="POST" action="{{ route('internal.meeting-minute-items.update', $item['public_id']) }}" class="flex items-center gap-2">@csrf @method('PATCH')<select name="status" class="rounded-lg border-slate-300 py-1.5 text-xs dark:border-slate-700 dark:bg-slate-950">@foreach(App\Enums\MeetingMinuteStatus::cases() as $statusOption)<option value="{{ $statusOption->value }}" @selected($item['status'] === $statusOption)>{{ $statusOption->label() }}</option>@endforeach</select><button class="text-xs font-bold text-orbit-700 dark:text-orbit-300">Save</button></form></div>@endforeach</div>
        </section>
    @endif

    @forelse ($open as $checkpoint)
        @php($question = $checkpoint->isInformationRequest())
        @php($urgent = ! $question && $checkpoint->daysLeft() <= 1)
        <section class="mb-4 rounded-2xl border bg-white p-5 dark:bg-slate-900 {{ $urgent ? 'border-rose-300 dark:border-rose-900' : 'border-slate-200 dark:border-slate-800' }}"
            aria-labelledby="checkpoint-{{ $checkpoint->public_id }}">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ $checkpoint->subject?->title }}</p>
                    <h2 id="checkpoint-{{ $checkpoint->public_id }}" class="mt-1 text-lg font-bold">{{ $checkpoint->task->title }}</h2>
                </div>
                <x-badge :tone="$question ? 'info' : ($urgent ? 'danger' : 'warning')">{{ $question ? 'Question from ITD' : $checkpoint->countdown() }}</x-badge>
            </div>

            @if ($checkpoint->reason)
                @if ($question)
                    <div class="mt-3 rounded-xl border border-orbit-200 bg-orbit-50/60 p-4 dark:border-orbit-900 dark:bg-orbit-950/40">
                        <p class="text-xs font-semibold text-orbit-700 dark:text-orbit-300">{{ $checkpoint->asker?->name ?? 'ITD' }} asked:</p>
                        <p class="mt-1 whitespace-pre-line text-sm leading-6">{{ $checkpoint->reason }}</p>
                    </div>
                @else
                    <p class="mt-3 text-sm text-slate-600 dark:text-slate-300">{{ $checkpoint->reason }}</p>
                @endif
            @endif

            @if ($checkpoint->task->description)
                <p class="mt-2 whitespace-pre-line text-sm leading-6 text-slate-500">{{ $checkpoint->task->description }}</p>
            @endif

            {{-- The consequence spelled out with a date. A deadline nobody knows about is not
                 a deadline, and "expires soon" is not a date. A question carries no deadline,
                 so it says nothing rather than inventing one. --}}
            @unless ($question)
                <p class="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-xs text-slate-500 dark:bg-slate-800/60">
                    If there is no response by <strong>{{ $checkpoint->expires_at->format('F j, Y') }}</strong>,
                    “{{ $checkpoint->subject?->title }}” is cancelled and ITD moves to the next request.
                </p>
            @endunless

            <form method="POST" action="{{ route('desk.validations.respond', $checkpoint) }}" class="mt-4 space-y-3"
                enctype="multipart/form-data" x-data="{ decision: 'approved' }">
                @csrf
                <x-form-errors :except="['decision', 'response_note']" />

                @if ($question)
                    <input type="hidden" name="decision" value="approved">
                    <div>
                        <x-label :for="'note_'.$checkpoint->public_id">Your answer</x-label>
                        <x-textarea :id="'note_'.$checkpoint->public_id" name="response_note" rows="4" placeholder="Answer ITD here. Mention file names or numbers where it helps." required>{{ old('response_note') }}</x-textarea>
                        <x-field-error name="response_note" />
                    </div>

                    @include('desk.validations._attachments', ['checkpoint' => $checkpoint])

                    <x-button>Send my answer</x-button>
                @else
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ([['approved', 'Looks correct', 'ITD continues the work.'], ['changes_requested', 'Changes required', 'Returned to ITD with your notes.']] as [$value, $label, $hint])
                            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                                <input type="radio" name="decision" value="{{ $value }}" x-model="decision" @checked($value === 'approved') class="mt-1 border-slate-300 text-orbit-600 focus:ring-orbit-500">
                                <span class="min-w-0"><span class="block text-sm font-semibold">{{ $label }}</span><span class="mt-0.5 block text-xs text-slate-500">{{ $hint }}</span></span>
                            </label>
                        @endforeach
                    </div>

                    <div x-show="decision === 'changes_requested'" x-cloak>
                        <x-label :for="'note_'.$checkpoint->public_id">What needs to change?</x-label>
                        <x-textarea :id="'note_'.$checkpoint->public_id" name="response_note" rows="3" placeholder="Explain what is incorrect or missing.">{{ old('response_note') }}</x-textarea>
                        <x-field-error name="response_note" />
                    </div>

                    @include('desk.validations._attachments', ['checkpoint' => $checkpoint])

                    <x-button>Submit my response</x-button>
                @endif
            </form>
        </section>
    @empty
        @if ($momActionItems['items'] === [])<x-empty-state icon="check" title="Nothing is waiting on you"
            description="When ITD asks you something, or finishes work that needs your confirmation, it appears here." />
        @endif
    @endforelse

    @if ($answered->isNotEmpty())
        <section class="mt-10" aria-labelledby="answered-title">
            <h2 id="answered-title" class="text-lg font-bold">Answered</h2>
            <div class="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white dark:border-slate-800 dark:bg-slate-900">
                @foreach ($answered as $checkpoint)
                    <div class="flex flex-wrap items-center gap-3 border-b border-slate-100 p-4 last:border-0 dark:border-slate-800">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-semibold">{{ $checkpoint->task->title }}</p>
                            <p class="mt-0.5 truncate text-xs text-slate-500">
                                {{ $checkpoint->subject?->title }}
                                @if ($checkpoint->responded_at)
                                    · answered {{ $checkpoint->responded_at->diffForHumans() }}
                                @endif
                            </p>
                        </div>
                        <x-badge :tone="$checkpoint->status->tone()">{{ $checkpoint->status->label() }}</x-badge>
                    </div>
                @endforeach
            </div>
        </section>
    @endif
@endsection
