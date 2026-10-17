<?php

namespace App\Modules\Foundation\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Modules\Foundation\Http\Resources\NotificationResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use OpenApi\Attributes as OA;

/**
 * The signed-in user's notifications, for apps. Same data as the web bell.
 */
#[OA\Schema(
    schema: 'Notification',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'title', type: 'string'),
        new OA\Property(property: 'body', type: 'string'),
        new OA\Property(property: 'level', type: 'string', enum: ['info', 'success', 'warning', 'error']),
        new OA\Property(property: 'url', type: 'string', nullable: true),
        new OA\Property(property: 'read_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
)]
class NotificationController extends Controller
{
    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'The notifications of the signed-in user, newest first',
        security: [['bearer' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'unread', description: 'Only unread notifications', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'page', in: 'query', schema: new OA\Schema(type: 'integer')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'A page of notifications', content: new OA\JsonContent(properties: [
                new OA\Property(property: 'data', type: 'array', items: new OA\Items(ref: '#/components/schemas/Notification')),
                new OA\Property(property: 'meta', type: 'object'),
            ])),
        ],
    )]
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->boolean('unread')
            ? $request->user()->unreadNotifications()
            : $request->user()->notifications();

        return NotificationResource::collection($notifications->paginate(20)->withQueryString());
    }

    #[OA\Post(
        path: '/api/v1/notifications/{id}/read',
        summary: 'Mark a notification as read',
        security: [['bearer' => []]],
        tags: ['Notifications'],
        parameters: [new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 204, description: 'Marked as read'),
            new OA\Response(response: 404, description: 'Not one of the user\'s notifications'),
        ],
    )]
    public function markAsRead(Request $request, string $notification): Response
    {
        $request->user()->notifications()->whereKey($notification)->firstOrFail()->markAsRead();

        return response()->noContent();
    }

    #[OA\Post(
        path: '/api/v1/notifications/read-all',
        summary: 'Mark every notification as read',
        security: [['bearer' => []]],
        tags: ['Notifications'],
        responses: [new OA\Response(response: 204, description: 'Marked as read')],
    )]
    public function markAllAsRead(Request $request): Response
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->noContent();
    }
}
