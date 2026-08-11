<?php

namespace App\Http\Controllers\InternalApi;

use App\Actions\Feature\CreateFeature;
use App\Http\Requests\Feature\StoreFeatureRequest;
use App\Jobs\GenerateTaskBreakdown;
use App\Models\Workspace;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class FeatureController extends Controller
{
    public function store(StoreFeatureRequest $request, Workspace $workspace, CreateFeature $create): JsonResponse|RedirectResponse
    {
        $system = $workspace->projects()
            ->systems()
            ->whereNull('archived_at')
            ->where('public_id', $request->string('system_public_id'))
            ->firstOrFail();

        $feature = $create->handle($system, $request->user(), $request->validated(), $request->ip());

        if ($request->boolean('generate_with_ai')) {
            GenerateTaskBreakdown::dispatch($feature);
        }

        return $this->success(
            $request,
            ['public_id' => $feature->public_id],
            'Feature created.',
            route('app.features.show', [$workspace, $system]),
            201,
        );
    }
}
