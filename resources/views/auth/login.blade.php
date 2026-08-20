@extends('layouts.guest')

@section('title', 'Sign in')

@section('bare')
    <main id="main-content" class="auth-split">
        <section class="auth-form-side" aria-labelledby="login-heading">
            <div class="auth-form-wrap">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 rounded-lg" aria-label="Elara home">
                    <img src="{{ asset('elara-favicon.svg') }}" alt="" class="size-11 shrink-0" aria-hidden="true">
                    <span class="leading-tight">
                        <span
                            class="block text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Elara</span>
                        <span class="block text-[10px] text-slate-500 dark:text-slate-400">Request-to-delivery
                            workspace</span>
                    </span>
                </a>

                <div class="mt-12">
                    <h1 id="login-heading" class="text-3xl font-extrabold tracking-tight text-slate-950 dark:text-white">
                        Sign in</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Continue to your projects,
                        requests, and team
                        workspace.</p>
                </div>

                <div class="mt-5 space-y-4">
                    <x-status />
                    <x-auth-errors />
                </div>

                <form method="POST" action="{{ route('login') }}" class="auth-form mt-6 space-y-4" x-data="{ submitting: false }"
                    x-on:submit="submitting = true">
                    @csrf

                    <div>
                        <x-label for="email" value="Email address" />
                        <x-input id="email" name="email" type="email" value="{{ old('email') }}"
                            placeholder="you@example.com" required autofocus autocomplete="username"
                            aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
                    </div>

                    <div>
                        <x-label for="password" value="Password" />
                        <x-input id="password" name="password" type="password" placeholder="Enter your password" required
                            autocomplete="current-password"
                            aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label class="flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300">
                            <input name="remember" type="checkbox" value="1"
                                class="rounded border-slate-300 bg-white text-orbit-500 dark:border-slate-600 dark:bg-slate-800 focus:ring-orbit-500">
                            Keep me signed in
                        </label>
                        <a href="{{ route('password.request') }}"
                            class="text-xs text-slate-600 dark:text-slate-300 underline decoration-slate-300 dark:decoration-slate-600 underline-offset-4 transition hover:text-orbit-600">Forgot
                            password?</a>
                    </div>

                    <x-button class="w-full" x-bind:disabled="submitting">
                        <span x-show="!submitting">Sign in</span>
                        <span x-cloak x-show="submitting">Signing in…</span>
                    </x-button>


                    {{-- <p class="text-center text-sm text-slate-600 dark:text-slate-300">
                        Don’t have an account?
                        <a href="{{ route('register') }}"
                            class="font-semibold text-orbit-600 transition hover:text-orbit-700">Create account</a>
                    </p> --}}
                </form>

                <ul class="mt-10 space-y-3 text-xs text-slate-600 dark:text-slate-300" aria-label="Orbitra benefits">
                    <li class="flex items-center gap-3"><x-icon name="tasks" class="size-4 text-orbit-500" /> Keep
                        requests and delivery work connected</li>
                    <li class="flex items-center gap-3"><x-icon name="calendar" class="size-4 text-orbit-500" /> Plan work
                        against real team capacity</li>
                    <li class="flex items-center gap-3"><x-icon name="performance" class="size-4 text-orbit-500" /> Track
                        progress, workload, and deadlines</li>
                </ul>
            </div>
        </section>

        @include('auth._visual', [
            'label' => 'Orbitra introduction',
            'eyebrow' => 'One delivery workspace',
            'heading' => 'Projects move forward, clearly managed.',
            'description' =>
                'Bring requests, approvals, schedules, tasks, and team progress into one focused workspace—so important work never falls off the radar.',
        ])
    </main>
@endsection
