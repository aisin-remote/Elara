@props(['src' => null, 'name', 'size' => 'size-10'])
@php
    $initials = str($name)->explode(' ')->filter()->take(2)->map(fn ($part) => str($part)->substr(0, 1))->join('');
    // Same name always lands on the same swatch; a collision between two people is cosmetic only.
    $palette = [
        'bg-orbit-100 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-200',
        'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-200',
        'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-200',
        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200',
        'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-200',
        'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-200',
        'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950 dark:text-fuchsia-200',
        'bg-teal-100 text-teal-700 dark:bg-teal-950 dark:text-teal-200',
    ];
    $tone = $palette[crc32(mb_strtolower(trim($name))) % count($palette)];
@endphp
@if($src)<img src="{{ $src }}" alt="{{ $name }}" title="{{ $name }}" {{ $attributes->class([$size, 'rounded-xl object-cover']) }}>@else<span role="img" aria-label="{{ $name }}" title="{{ $name }}" {{ $attributes->class([$size, $tone, 'inline-grid place-items-center rounded-xl text-xs font-bold']) }}>{{ str($initials)->upper() }}</span>@endif
