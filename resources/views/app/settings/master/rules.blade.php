@extends('layouts.app')

@section('title', 'Request rules')
@section('page-title', 'Settings')
@section('master-title', 'Request rules')

@section('content')
    @include('app.settings._navigation')
    @include('app.settings.master._navigation')

    <form method="POST" action="{{ route('internal.master.rules.save', $workspace) }}" class="max-w-2xl space-y-5 rounded-2xl border border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900">
        @csrf

        <div>
            <x-label for="validation_window_days">Validation window (days)</x-label>
            <x-input id="validation_window_days" type="number" min="1" max="60" name="validation_window_days" value="{{ old('validation_window_days', $values['validation_window_days']) }}" required />
            <x-field-error name="validation_window_days" />
            <p class="mt-2 text-xs text-slate-500">How long a requester has to answer a checkpoint before the work is taken down. Changing this affects the next checkpoint, never one already counting down.</p>
        </div>

        <div>
            <x-label for="pic_grace_days">PIC grace period (days)</x-label>
            <x-input id="pic_grace_days" type="number" min="0" max="90" name="pic_grace_days" value="{{ old('pic_grace_days', $values['pic_grace_days']) }}" required />
            <x-field-error name="pic_grace_days" />
            <p class="mt-2 text-xs text-slate-500">How much later than the next best person a system's PIC may be and still keep the work. Set it to zero and work always goes to whoever is free first.</p>
        </div>

        <div>
            <x-label for="horizon_days">Scheduling horizon (days)</x-label>
            <x-input id="horizon_days" type="number" min="7" max="365" name="horizon_days" value="{{ old('horizon_days', $values['horizon_days']) }}" required />
            <x-field-error name="horizon_days" />
            <p class="mt-2 text-xs text-slate-500">How far ahead the planner looks before giving up and leaving a request in the queue.</p>
        </div>

        <div>
            <x-label for="ai_model">Task breakdown model</x-label>
            <x-input id="ai_model" name="ai_model" value="{{ old('ai_model', $values['ai_model']) }}" maxlength="100" />
            <x-field-error name="ai_model" />
            <p class="mt-2 text-xs text-slate-500">Which model drafts task breakdowns. Leave blank to follow <code>OPENAI_MODEL</code>. Plans already drafted keep the model that actually produced them.</p>
        </div>

        <x-button>Save rules</x-button>
    </form>
@endsection
