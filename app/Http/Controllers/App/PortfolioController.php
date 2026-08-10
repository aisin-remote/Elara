<?php

namespace App\Http\Controllers\App;

use App\Http\Controllers\Controller;
use App\Models\Workspace;
use App\Services\Planning\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortfolioController extends Controller
{
    public function index(Request $request, Workspace $workspace, PortfolioService $portfolio): View
    {
        $this->authorize('viewReport', $workspace);

        return view('app.portfolio.index', [
            'workspace' => $workspace,
            'portfolio' => $portfolio->forWorkspace($workspace, $request->user()),
        ]);
    }
}
