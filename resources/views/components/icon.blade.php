@props(['name'])

<svg {{ $attributes->merge(['class' => 'size-4 shrink-0', 'viewBox' => '0 0 24 24', 'fill' => 'none', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'stroke-linecap' => 'round', 'stroke-linejoin' => 'round', 'aria-hidden' => 'true']) }}>
    @switch($name)
        @case('dashboard') <rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/> @break
        @case('projects') <path d="M4 7h6l2 2h8v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2Z"/><path d="M4 7V5a2 2 0 0 1 2-2h4l2 2h4"/> @break
        @case('tasks') <path d="m4 6 2 2 4-4"/><path d="M13 6h7"/><path d="m4 13 2 2 4-4"/><path d="M13 13h7"/><path d="M4 20h16"/> @break
        @case('team') <circle cx="9" cy="8" r="3"/><path d="M3 20c0-4 2-6 6-6s6 2 6 6"/><path d="M16 5a3 3 0 0 1 0 6M18 14c2 .7 3 2.7 3 6"/> @break
        @case('settings') <circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.9l.1.1-2.8 2.8-.1-.1a1.7 1.7 0 0 0-1.9-.3 1.7 1.7 0 0 0-1 1.6v.2h-4V21a1.7 1.7 0 0 0-1-1.6 1.7 1.7 0 0 0-1.9.3l-.1.1L4.2 17l.1-.1a1.7 1.7 0 0 0 .3-1.9A1.7 1.7 0 0 0 3 14H2.8v-4H3a1.7 1.7 0 0 0 1.6-1 1.7 1.7 0 0 0-.3-1.9L4.2 7 7 4.2l.1.1A1.7 1.7 0 0 0 9 4.6a1.7 1.7 0 0 0 1-1.6v-.2h4V3a1.7 1.7 0 0 0 1 1.6 1.7 1.7 0 0 0 1.9-.3l.1-.1L19.8 7l-.1.1a1.7 1.7 0 0 0-.3 1.9 1.7 1.7 0 0 0 1.6 1h.2v4H21a1.7 1.7 0 0 0-1.6 1Z"/> @break
        @case('supporting') <path d="M14.7 6.3a4 4 0 0 0-5-5l2.1 2.1-2.4 2.4-2.1-2.1a4 4 0 0 0 5 5L4 15.1a2.1 2.1 0 1 0 3 3l7.7-7.7a4 4 0 0 0 5-5l-2.1 2.1-2.4-2.4Z"/> @break
        @case('search') <circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/> @break
        @case('plus') <path d="M12 5v14M5 12h14"/> @break
        @case('chevron-right') <path d="m9 5 7 7-7 7"/> @break
        @case('sun') <circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/> @break
        @case('moon') <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/> @break
        @case('trash') <path d="M4 7h16"/><path d="M10 11v6M14 11v6"/><path d="M6 7l1 12a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2l1-12"/><path d="M9 7V5a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/> @break
        @case('sparkles') <path d="M12 3l1.8 4.7L18.5 9.5 13.8 11.3 12 16l-1.8-4.7L5.5 9.5l4.7-1.8Z"/><path d="M18 15l.9 2.1 2.1.9-2.1.9L18 21l-.9-2.1-2.1-.9 2.1-.9Z"/> @break
        @case('check') <path d="M20 6 9 17l-5-5"/> @break
        @case('hourglass') <path d="M5 2h14M5 22h14"/><path d="M7 2v4.17a2 2 0 0 0 .59 1.42L12 12l4.41-4.41A2 2 0 0 0 17 6.17V2M7 22v-4.17a2 2 0 0 1 .59-1.42L12 12l4.41 4.41a2 2 0 0 1 .59 1.42V22"/> @break
        @case('alert') <path d="M12 9v4M12 17h.01"/><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/> @break
        @case('info') <circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/> @break
        @case('close') <path d="M18 6 6 18M6 6l12 12"/> @break
        @case('list') <path d="M8 6h13M8 12h13M8 18h13"/><path d="M3 6h.01M3 12h.01M3 18h.01"/> @break
        @case('board') <rect x="3" y="4" width="7" height="16" rx="2"/><rect x="14" y="4" width="7" height="10" rx="2"/> @break
        @case('calendar') <rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/> @break
        @case('performance') <path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/><path d="m3 7 6-4 6 5 6-5"/> @break
        @case('arrow-up') <path d="m6 15 6-6 6 6"/> @break
        @case('arrow-down') <path d="m6 9 6 6 6-6"/> @break
        @case('clock') <circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/> @break
        @case('refresh') <path d="M20 11a8 8 0 1 0-2.3 5.7"/><path d="M20 4v7h-7"/> @break
        @case('download') <path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M5 19h14"/> @break
        @case('files') <path d="M4 7h6l2 2h8v10a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V7Z"/><path d="M4 7V5a2 2 0 0 1 2-2h4l2 2h4"/> @break
        @case('messages') <path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4Z"/><path d="M8 9h8M8 13h5"/> @break
        @case('bell') <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/> @break
        @case('help') <circle cx="12" cy="12" r="9"/><path d="M9.6 9a2.5 2.5 0 1 1 3.6 2.25c-.8.4-1.2.9-1.2 1.75"/><path d="M12 17h.01"/> @break
        @case('link') <path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/> @break
        @case('dots-horizontal') <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/> @break
        @case('chat') <path d="m3 21 1.9-5.7a8.5 8.5 0 1 1 3.8 3.8z"/> @break
        @default <circle cx="12" cy="12" r="9"/>
    @endswitch
</svg>
