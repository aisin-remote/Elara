@if (session('status'))
    @php
        $message = session('status') === 'verification-link-sent'
            ? 'A new verification link has been sent to your email address.'
            : session('status');
    @endphp
    {{-- Flash messages surface as a toast; <x-toast /> in the layout listens for this. --}}
    {{-- $nextTick: the toast stack sits later in the DOM, so its listener is not bound yet during init. --}}
    <div hidden x-init="$nextTick(() => $dispatch('orbitra-toast', { message: @js($message), title: 'Success', variant: 'success' }))"></div>
@endif
