@extends('layouts.marketing')

@php
    $documents = [
        'privacy' => [
            'title' => 'Privacy notice',
            'intro' => 'This notice describes the data Orbitra needs to operate a workspace and the controls available to account holders.',
            'sections' => [
                ['Data we process', 'Account identity, workspace content, task and project activity, private file metadata, session security records, and provider tokens required for integrations.'],
                ['How data is used', 'Data is used to authenticate users, deliver requested collaboration features, enforce permissions, and protect the service.'],
                ['Storage and sharing', 'Private files are delivered through authorized application routes. Integration data is shared only with the provider action initiated by an authorized workspace owner.'],
                ['Your controls', 'Account holders can update profile data, revoke sessions, disconnect integrations, manage notifications, and ask the deployment operator for export or deletion assistance.'],
            ],
        ],
        'terms' => [
            'title' => 'Terms of use',
            'intro' => 'These baseline terms describe responsible use of an Orbitra deployment. The operator should replace them with jurisdiction-specific terms before commercial launch.',
            'sections' => [
                ['Account responsibility', 'Keep credentials and recovery codes secure, use accurate account information, and promptly revoke sessions or integrations you no longer recognize.'],
                ['Acceptable use', 'Do not misuse the service, access another workspace without authorization, upload unlawful content, or interfere with service availability.'],
                ['Workspace content', 'Workspace owners remain responsible for content their team stores and for configuring retention, backups, and connected providers.'],
                ['Service operation', 'Features may depend on configured mail, queue, broadcast, Stripe, and OAuth providers. Deployment-specific availability terms are set by the operator.'],
            ],
        ],
        'accessibility' => [
            'title' => 'Accessibility statement',
            'intro' => 'Orbitra is designed for keyboard operation, readable focus states, semantic forms, responsive layouts, and reduced-motion preferences.',
            'sections' => [
                ['Supported interaction', 'Navigation, forms, dialogs, tables, notifications, and core project actions are designed to remain usable without a pointing device.'],
                ['Responsive access', 'Application views reflow or provide scrolling and agenda fallbacks so essential work remains available at a 375-pixel viewport.'],
                ['Motion and contrast', 'System reduced-motion preferences are respected and the visual system targets WCAG AA contrast for text and controls.'],
                ['Feedback', 'If an accessibility barrier is found, contact the operator of this Orbitra deployment and include the affected page and assistive technology.'],
            ],
        ],
    ];
    $content = $documents[$document];
@endphp

@section('title', $content['title'])

@section('content')
    <section class="mx-auto max-w-4xl px-5 py-16 sm:py-24 lg:px-8">
        <p class="text-sm font-bold uppercase tracking-[0.16em] text-orbit-600">Orbitra</p>
        <h1 class="mt-3 text-4xl font-bold tracking-tight sm:text-5xl">{{ $content['title'] }}</h1>
        <p class="mt-6 text-lg leading-8 text-slate-600 dark:text-slate-300">{{ $content['intro'] }}</p>
        <p class="mt-3 text-sm text-slate-500">Last updated {{ now()->format('F Y') }}</p>
        <div class="mt-12 space-y-8">
            @foreach($content['sections'] as [$heading, $body])
                <section class="border-t border-slate-200 pt-7 dark:border-slate-800"><h2 class="text-xl font-bold">{{ $heading }}</h2><p class="mt-3 leading-7 text-slate-600 dark:text-slate-300">{{ $body }}</p></section>
            @endforeach
        </div>
    </section>
@endsection
