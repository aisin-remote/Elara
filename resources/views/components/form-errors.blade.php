@props(['except' => []])

@php
    // Everything the surrounding form does not already show next to a field. Without this a
    // rule thrown from an Action — an illegal status transition, for example — lands on a key
    // no field is bound to, and the page silently reloads as if the button were broken.
    $unattached = collect($errors->keys())
        ->reject(fn (string $key) => in_array($key, (array) $except, true))
        ->flatMap(fn (string $key) => $errors->get($key));
@endphp

@if ($unattached->isNotEmpty())
    <x-alert variant="error" :dismissible="false" tabindex="-1" class="max-w-none">
        @if ($unattached->count() === 1)
            {{ $unattached->first() }}
        @else
            <ul class="list-disc space-y-1 pl-5">
                @foreach ($unattached as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        @endif
    </x-alert>
@endif
