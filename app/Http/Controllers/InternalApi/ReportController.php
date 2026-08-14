<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Report\PerformanceRequest;
use App\Models\Workspace;
use App\Services\ReportExportService;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function csv(PerformanceRequest $request, Workspace $workspace, ReportExportService $export): Response
    {
        return $export->csv($workspace, $request->user(), $request->validated());
    }

    public function pdf(PerformanceRequest $request, Workspace $workspace, ReportExportService $export): Response
    {
        return $export->pdf($workspace, $request->user(), $request->validated());
    }
}
