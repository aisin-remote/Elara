<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PerformanceRequest;
use App\Models\Workspace;
use App\Services\PlanEntitlementService;
use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function csv(PerformanceRequest $request, Workspace $workspace, ReportExportService $export, PlanEntitlementService $entitlements): Response
    {
        $entitlements->assertCanExport($workspace);

        return $export->csv($workspace, $request->user(), $request->validated());
    }

    public function pdf(PerformanceRequest $request, Workspace $workspace, ReportExportService $export, PlanEntitlementService $entitlements): Response
    {
        $entitlements->assertCanExport($workspace);

        return $export->pdf($workspace, $request->user(), $request->validated());
    }
}
