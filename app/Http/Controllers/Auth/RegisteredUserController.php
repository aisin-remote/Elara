<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        abort_if(config('organization.jit_auth'), 404);

        return view('auth.register');
    }

    public function store(RegisterRequest $request): RedirectResponse
    {
        abort_if(config('organization.jit_auth'), 404);

        $user = DB::transaction(fn () => User::create([
            ...$request->safe()->only('first_name', 'last_name', 'email', 'password'),
            'email_verified_at' => config('orbitra.email_verification') ? null : now(),
        ]));

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route(config('orbitra.email_verification') ? 'verification.notice' : 'app.dashboard');
    }
}
