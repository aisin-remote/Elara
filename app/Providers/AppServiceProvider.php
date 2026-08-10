<?php

namespace App\Providers;

use App\Contracts\TaskBreakdownGenerator;
use App\Services\Ai\OpenAiTaskBreakdown;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Cashier\Cashier;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Cashier::ignoreRoutes();

        $this->app->bind(TaskBreakdownGenerator::class, OpenAiTaskBreakdown::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(fn () => Password::min(12)->mixedCase()->numbers()->symbols());

        VerifyEmail::createUrlUsing(fn ($user) => URL::temporarySignedRoute(
            'verification.verify',
            now()->addMinutes(60),
            ['id' => $user->public_id, 'hash' => sha1($user->getEmailForVerification())]
        ));
    }
}
