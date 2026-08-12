<?php

namespace App\Http\Controllers\InternalApi;

use App\Http\Controllers\Controller;
use App\Http\Requests\Ai\DraftDescriptionRequest;
use App\Models\Workspace;
use App\Services\Ai\OpenAiDescriptionDraft;
use Illuminate\Http\JsonResponse;
use RuntimeException;

class AiDescriptionController extends Controller
{
    public function store(
        DraftDescriptionRequest $request,
        Workspace $workspace,
        OpenAiDescriptionDraft $draft,
    ): JsonResponse {
        $system = $request->filled('system_public_id')
            ? $workspace->projects()
                ->systems()
                ->whereNull('archived_at')
                ->where('public_id', $request->string('system_public_id'))
                ->firstOrFail()
            : null;

        try {
            $description = $draft->generate(
                $workspace,
                $request->user(),
                $request->string('kind')->toString(),
                $request->string('name')->toString(),
                $request->string('description')->toString(),
                $system,
            );
        } catch (RuntimeException $exception) {
            report($exception);

            return response()->json([
                'message' => 'AI could not expand the description right now. Please try again.',
            ], 503);
        }

        return response()->json([
            'message' => 'Description generated.',
            'data' => ['description' => $description],
        ]);
    }
}
