@props(['size' => 'default'])

@php
    $variants = [
        'default' => [
            'wrapper' => 'gap-3 rounded-lg',
            'icon' => 'size-10',
            'text' => 'text-xl',
        ],
        'sidebar' => [
            'wrapper' => 'gap-2.5 rounded-lg',
            'icon' => 'size-9',
            'text' => 'text-lg',
        ],
    ];

    $variant = $variants[$size] ?? $variants['default'];
@endphp

<a {{ $attributes->merge(['href' => route('home'), 'class' => 'inline-flex items-center '.$variant['wrapper']]) }}>
    {{-- The mark is the favicon file itself, so the tab, the sidebar, and the documentation
         cover can never drift apart. --}}
    <img src="{{ asset('elara-favicon.svg') }}" alt="" class="shrink-0 {{ $variant['icon'] }}" aria-hidden="true">
    <span class="font-bold tracking-tight {{ $variant['text'] }}">Elara</span>
</a>
