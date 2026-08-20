@extends('layouts.guest')

@section('title', 'Create account')

@section('bare')
    <main id="main-content" class="auth-split auth-split-register">
        <section class="auth-form-side" aria-labelledby="register-heading">
            <div class="auth-form-wrap auth-form-wrap-wide">
                <a href="{{ route('home') }}" class="inline-flex items-center gap-3 rounded-lg" aria-label="Elara home">
                    <img src="{{ asset('elara-favicon.svg') }}" alt="" class="size-11 shrink-0" aria-hidden="true">
                    <span class="leading-tight">
                        <span class="block text-xl font-extrabold tracking-tight text-slate-950 dark:text-white">Elara</span>
                        <span class="block text-[10px] text-slate-500 dark:text-slate-400">Request-to-delivery workspace</span>
                    </span>
                </a>

                <div class="mt-8">
                    <h1 id="register-heading" class="text-3xl font-extrabold tracking-tight text-slate-950 dark:text-white">Create your Orbitra account</h1>
                    <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Set up your profile and start organizing work in Orbitra.</p>
                </div>

                <div class="mt-4"><x-auth-errors /></div>

                <form method="POST" action="{{ route('register') }}" class="auth-form mt-5" x-data="{ submitting: false }" x-on:submit="submitting = true">
                    @csrf

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <x-label for="first_name" value="First name" />
                            <x-input id="first_name" name="first_name" value="{{ old('first_name') }}" placeholder="First name" required autofocus autocomplete="given-name" aria-invalid="{{ $errors->has('first_name') ? 'true' : 'false' }}" />
                        </div>
                        <div>
                            <x-label for="last_name" value="Last name" />
                            <x-input id="last_name" name="last_name" value="{{ old('last_name') }}" placeholder="Last name" required autocomplete="family-name" aria-invalid="{{ $errors->has('last_name') ? 'true' : 'false' }}" />
                        </div>

                        <div class="sm:col-span-2">
                            <x-label for="email" value="Email address" />
                            <x-input id="email" name="email" type="email" value="{{ old('email') }}" placeholder="you@example.com" required autocomplete="username" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}" />
                        </div>

                        <div>
                            <x-label for="password" value="Password" />
                            <x-input id="password" name="password" type="password" placeholder="At least 12 characters" required autocomplete="new-password" aria-describedby="password-help" aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" />
                        </div>
                        <div>
                            <x-label for="password_confirmation" value="Confirm password" />
                            <x-input id="password_confirmation" name="password_confirmation" type="password" placeholder="Repeat password" required autocomplete="new-password" />
                        </div>
                    </div>

                    <p id="password-help" class="mt-3 text-xs leading-5 text-slate-500 dark:text-slate-400">Use upper and lowercase letters, a number, and a symbol.</p>

                    <x-button class="mt-6 w-full" x-bind:disabled="submitting">
                        <span x-show="!submitting">Create account</span>
                        <span x-cloak x-show="submitting">Creating account…</span>
                    </x-button>

                    <p class="mt-4 text-center text-sm text-slate-600 dark:text-slate-300">
                        Already have an account?
                        <a href="{{ route('login') }}" class="font-semibold text-orbit-600 transition hover:text-orbit-700">Sign in</a>
                    </p>
                </form>
            </div>
        </section>

        @include('auth._visual', [
            'label' => 'Orbitra registration information',
            'eyebrow' => 'Start with clarity',
            'heading' => 'Your delivery workspace, ready from day one.',
            'description' => 'Create your account and keep projects, requests, schedules, and team collaboration connected in one place.',
        ])
    </main>
@endsection
