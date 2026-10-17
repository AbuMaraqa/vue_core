<?php

namespace App\Modules\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Foundation\Http\Resources\NotificationResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * The signed-in user's notifications. Every lookup goes through the user's
 * own notifications, so nobody can read or change someone else's.
 */
class NotificationController extends Controller
{
    public function index(Request $request): Response
    {
        $notifications = QueryBuilder::for($request->user()->notifications())
            ->allowedFilters(
                AllowedFilter::callback('status', fn (Builder $query, string $status) => match ($status) {
                    'unread' => $query->whereNull('read_at'),
                    'read' => $query->whereNotNull('read_at'),
                    default => $query,
                }),
            )
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/notifications/Index', [
            'notifications' => NotificationResource::collection($notifications),
            'filters' => [
                'status' => $request->input('filter.status'),
            ],
        ]);
    }

    /**
     * The newest notifications for the dropdown of the notification bell.
     */
    public function latest(Request $request): JsonResponse
    {
        return response()->json([
            'unread_count' => $request->user()->unreadNotifications()->count(),
            'notifications' => NotificationResource::collection($request->user()->notifications()->limit(8)->get())->resolve($request),
        ]);
    }

    /**
     * Mark the notification as read and go to the page it is about.
     */
    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $this->find($request, $notification);
        $notification->markAsRead();

        $url = NotificationResource::make($notification)->resolve($request)['url'];

        return $url ? redirect($url) : back();
    }

    public function markAllAsRead(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All notifications were marked as read.')]);

        return back();
    }

    public function destroy(Request $request, string $notification): RedirectResponse
    {
        $this->find($request, $notification)->delete();

        return back();
    }

    protected function find(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }
}
