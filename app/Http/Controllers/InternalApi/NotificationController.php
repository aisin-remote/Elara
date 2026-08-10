<?php

namespace App\Http\Controllers\InternalApi;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = $request->user()->notifications()
            ->when($request->string('workspace_public_id')->toString(), fn ($builder, string $workspace) => $builder
                ->where('data->workspace_public_id', $workspace));
        $notifications = (clone $query)->latest()->paginate(min(50, max(1, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($notifications->items())->map(fn ($notification) => $this->payload($notification)),
            'meta' => [
                'unread_count' => (clone $query)->whereNull('read_at')->count(),
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
            ],
        ]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $item = $request->user()->notifications()->whereKey($notification)->firstOrFail();
        $item->markAsRead();

        return response()->json(['data' => $this->payload($item->fresh()), 'message' => 'Notification marked as read.']);
    }

    public function readAll(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    private function payload($notification): array
    {
        return [
            'id' => $notification->id,
            ...$notification->data,
            'read_at' => $notification->read_at?->toIso8601String(),
            'created_at' => $notification->created_at?->toIso8601String(),
        ];
    }
}
