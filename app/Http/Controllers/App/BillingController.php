<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\BillingService;
use App\Services\PlanEntitlementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BillingController extends Controller
{
    public function index(Workspace $workspace, PlanEntitlementService $entitlements, BillingService $billing): View
    {
        $this->authorize('viewBilling', $workspace);
        $invoiceResult = $billing->invoices($workspace->owner);

        return view('app.settings.subscription', [
            'workspace' => $workspace,
            'plans' => config('plans.plans'),
            'currentPlan' => $entitlements->details($workspace),
            'subscription' => $billing->subscription($workspace->owner, $workspace),
            'invoices' => $invoiceResult['items'],
            'invoiceError' => $invoiceResult['error'],
            'contactSalesUrl' => config('plans.contact_sales_url'),
        ]);
    }

    public function default(): RedirectResponse
    {
        $workspace = request()->user()->ownedWorkspaces()->first();
        abort_unless($workspace, 404);

        return redirect()->route('app.settings.subscription', $workspace);
    }
}
