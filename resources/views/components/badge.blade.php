@props(['tone' => 'slate'])
@php($tones = ['slate' => 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200', 'orbit' => 'bg-orbit-50 text-orbit-700 dark:bg-orbit-950 dark:text-orbit-200', 'success' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200', 'danger' => 'bg-rose-50 text-rose-700 dark:bg-rose-950 dark:text-rose-200'])
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold', $tones[$tone] ?? $tones['slate']]) }}>{{ $slot }}</span>
