@props(['size' => 'default'])

@php
    $variants = [
        'default' => [
            'wrapper' => 'gap-3 rounded-lg',
            'icon' => 'size-10 rounded-2xl text-lg',
            'text' => 'text-xl',
        ],
        'sidebar' => [
            'wrapper' => 'gap-2.5 rounded-lg',
            'icon' => 'size-9 rounded-xl text-base',
            'text' => 'text-lg',
        ],
    ];

    $variant = $variants[$size] ?? $variants['default'];
@endphp

<a {{ $attributes->merge(['href' => route('home'), 'class' => 'inline-flex items-center '.$variant['wrapper']]) }}>
    <span class="grid shrink-0 place-items-center bg-gradient-to-br from-orbit-400 to-indigo-600 font-bold text-white shadow-lg shadow-orbit-500/20 {{ $variant['icon'] }}" aria-hidden="true">E</span>
    <span class="font-bold tracking-tight {{ $variant['text'] }}">Elara</span>
</a>
