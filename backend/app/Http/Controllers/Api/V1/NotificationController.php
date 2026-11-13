<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListNotificationsRequest;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService)
    {
    }

    /**
     * API-NOTI-01: GET /api/v1/notifications
     */
    public function index(ListNotificationsRequest $request): JsonResponse
    {
        $result = $this->notificationService->index(
            $request->user(),
            $request->validated()
        );

        $paginator = $result['paginator'];

        return response()->json([
            'data' => [
                'items'        => NotificationResource::collection($paginator->items())->resolve($request),
                'unread_count' => $result['unread_count'],
                'pagination'   => [
                    'current_page' => $paginator->currentPage(),
                    'per_page'     => $paginator->perPage(),
                    'total'        => $paginator->total(),
                    'last_page'    => $paginator->lastPage(),
                ],
            ],
        ]);
    }

    /**
     * API-NOTI-02: PATCH /api/v1/notifications/{id}/read
     */
    public function markRead(Request $request, int $id): JsonResponse
    {
        $notification = $this->notificationService->markRead($request->user(), $id);

        return response()->json([
            'data' => [
                'id'      => $notification->id,
                'read_at' => $notification->read_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * API-NOTI-03: POST /api/v1/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $countUpdated = $this->notificationService->markAllRead($request->user());

        return response()->json([
            'data' => [
                'count_updated' => $countUpdated,
            ],
        ]);
    }
}
