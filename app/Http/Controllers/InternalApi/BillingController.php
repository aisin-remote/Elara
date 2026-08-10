<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Requests\Billing\CheckoutRequest;
use App\Http\Requests\Billing\WorkspaceBillingRequest;
use App\Models\Workspace;
use App\Services\BillingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class BillingController extends Controller
{
    public function checkout(CheckoutRequest $request, BillingService $billing): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspace($request->string('workspace_public_id')->toString());
        $this->authorize('manageBilling', $workspace);
        abort_if($billing->subscription($workspace->owner, $workspace)?->valid(), 409, 'Use change plan for an active subscription.');
        $price = config('plans.plans.'.$request->string('plan').'.'.$request->string('interval'));
        if (! $price) {
            throw ValidationException::withMessages(['plan' => 'This plan is not available for self-service checkout.']);
        }

        $url = $billing->checkout($workspace->owner, $workspace, $price);

        return $this->success($request, ['url' => $url], 'Checkout created.', $url, 201);
    }

    public function portal(WorkspaceBillingRequest $request, BillingService $billing): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspace($request->string('workspace_public_id')->toString());
        $this->authorize('manageBilling', $workspace);
        abort_unless($workspace->owner->stripe_id, 409, 'No Stripe customer is connected.');
        $url = $billing->portal($workspace->owner, $workspace);

        return $this->success($request, ['url' => $url], 'Billing portal opened.', $url);
    }

    public function change(CheckoutRequest $request, BillingService $billing): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspace($request->string('workspace_public_id')->toString());
        $this->authorize('manageBilling', $workspace);
        $subscription = $billing->subscription($workspace->owner, $workspace);
        abort_unless($subscription && $subscription->valid(), 409, 'There is no active subscription to change.');
        $price = config('plans.plans.'.$request->string('plan').'.'.$request->string('interval'));
        if (! $price) {
            throw ValidationException::withMessages(['plan' => 'This plan is not available for self-service changes.']);
        }
        $billing->swap($workspace->owner, $workspace, $price);

        return $this->success($request, null, 'Subscription changed.', route('app.settings.subscription', $workspace));
    }

    public function cancel(WorkspaceBillingRequest $request, BillingService $billing): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspace($request->string('workspace_public_id')->toString());
        $this->authorize('manageBilling', $workspace);
        abort_unless($billing->subscription($workspace->owner, $workspace)?->valid(), 409);
        $billing->cancel($workspace->owner, $workspace);

        return $this->success($request, null, 'Subscription will end after the current period.', route('app.settings.subscription', $workspace));
    }

    public function resume(WorkspaceBillingRequest $request, BillingService $billing): JsonResponse|RedirectResponse
    {
        $workspace = $this->workspace($request->string('workspace_public_id')->toString());
        $this->authorize('manageBilling', $workspace);
        abort_unless($billing->subscription($workspace->owner, $workspace)?->onGracePeriod(), 409);
        $billing->resume($workspace->owner, $workspace);

        return $this->success($request, null, 'Subscription resumed.', route('app.settings.subscription', $workspace));
    }

    private function workspace(string $publicId): Workspace
    {
        return Workspace::where('public_id', $publicId)
            ->whereHas('memberships', fn ($query) => $query->where('user_id', auth()->id())->where('status', 'active'))
            ->firstOrFail();
    }
}
