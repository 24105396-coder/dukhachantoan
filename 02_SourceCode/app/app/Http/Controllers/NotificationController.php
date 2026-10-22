<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationItem;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Hộp thông báo trong ứng dụng của người dùng đăng nhập. */
class NotificationController extends Controller
{
    public const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->filters($request, ['level', 'unread']);
        $notifications = $user->notifications()->where('channel', Notification::CHANNEL_INAPP)
            ->whereNotIn('status', [Notification::STATUS_CANCELLED])
            ->with('alert.translations', 'trip')
            ->when($filters['level'], fn ($q, $v) => $q->where('level', (int) $v))
            ->when($filters['unread'], fn ($q) => $q->whereNull('read_at'))
            ->latest('id')->paginate(self::PER_PAGE)->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationItem::paginate($notifications),
            'unread' => $user->unreadNotifications()->count(),
            'filters' => $filters,
        ]);
    }

    public function read(Request $request, NotificationService $service, Notification $notification): RedirectResponse
    {
        $this->authorize('update', $notification);
        $service->markRead($notification);

        return back();
    }

    public function readAll(Request $request, NotificationService $service): RedirectResponse
    {
        $count = $service->markAllRead($request->user());

        return back()->with('success', __('messages.notifications.all_read', ['count' => $count]));
    }
}
