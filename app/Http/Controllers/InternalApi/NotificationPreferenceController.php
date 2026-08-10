<?php

namespace App\Http\Controllers\InternalApi;

use App\Models\Workspace;
use App\Services\NotificationPreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function update(Request $request, NotificationPreferenceService $preferences): JsonResponse
    {
        $validated = $request->validate([
            'workspace_public_id' => ['required', 'string', 'size:26'],
            'preferences' => ['required', 'array'],
            'preferences.*.mail' => ['sometimes', 'boolean'],
            'preferences.*.in_app' => ['sometimes', 'boolean'],
            'preferences.*.push' => ['sometimes', 'boolean'],
        ]);
        $workspace = Workspace::query()->where('public_id', $validated['workspace_public_id'])->firstOrFail();
        $this->authorize('view', $workspace);

        return response()->json([
            'data' => $preferences->update($request->user(), $workspace, $validated['preferences']),
            'message' => 'Notification preferences saved.',
        ]);
    }
}
