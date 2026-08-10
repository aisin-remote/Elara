<?php

namespace App\Services;

use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Stripe\Exception\ApiErrorException;

class BillingService
{
    public function checkout(User $user, Workspace $workspace, string $price): string
    {
        return $user->newSubscription($this->subscriptionType($workspace), $price)
            ->trialDays(config('plans.trial_days'))
            ->checkout([
                'success_url' => route('app.settings.subscription', $workspace).'?checkout=success',
                'cancel_url' => route('app.settings.subscription', $workspace).'?checkout=cancelled',
                'subscription_data' => ['metadata' => ['workspace_public_id' => $workspace->public_id]],
            ])->url;
    }

    public function portal(User $user, Workspace $workspace): string
    {
        return $user->billingPortalUrl(route('app.settings.subscription', $workspace));
    }

    public function swap(User $user, Workspace $workspace, string $price): void
    {
        $user->subscription($this->subscriptionType($workspace))?->swap($price);
    }

    public function cancel(User $user, Workspace $workspace): void
    {
        $user->subscription($this->subscriptionType($workspace))?->cancel();
    }

    public function resume(User $user, Workspace $workspace): void
    {
        $user->subscription($this->subscriptionType($workspace))?->resume();
    }

    public function subscription(User $user, Workspace $workspace): mixed
    {
        return $user->subscription($this->subscriptionType($workspace));
    }

    public function invoices(User $user): array
    {
        if (! $user->stripe_id || ! config('cashier.secret')) {
            return ['items' => [], 'error' => null];
        }

        try {
            return ['items' => $user->invoices(), 'error' => null];
        } catch (ApiErrorException $exception) {
            Log::warning('Stripe invoice retrieval failed.', ['user_id' => $user->id, 'stripe_error' => $exception->getStripeCode()]);

            return ['items' => [], 'error' => 'Invoice history is temporarily unavailable.'];
        }
    }

    private function subscriptionType(Workspace $workspace): string
    {
        return 'workspace:'.$workspace->public_id;
    }
}
