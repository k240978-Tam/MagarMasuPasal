<?php

namespace Modules\Notification\Http\Controllers\Api;

use App\Http\Concerns\ApiResponds;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationApiController extends Controller
{
    use ApiResponds;

    public function index(Request $request): JsonResponse
    {
        $notifications = $request->user()->notifications()->paginate($request->integer('per_page', 25));

        return $this->respond(
            $notifications->getCollection()->map(fn ($n) => [
                'id' => $n->id,
                'type' => class_basename($n->type),
                'data' => $n->data,
                'read_at' => $n->read_at?->toIso8601String(),
                'created_at' => $n->created_at->toIso8601String(),
            ]),
            meta: ['total' => $notifications->total(), 'per_page' => $notifications->perPage()],
        );
    }

    public function markRead(string $id, Request $request): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return $this->respond(['message' => 'Marked as read.']);
    }
}
