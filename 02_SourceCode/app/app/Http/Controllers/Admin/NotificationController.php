<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BroadcastRequest;
use App\Http\Resources\NotificationItem;
use App\Models\Notification;
use App\Models\Zone;
use App\Services\DashboardService;
use App\Services\LookupService;
use App\Services\MatchingService;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Hàng đợi thông báo: theo dõi lần thử, gửi lại, phát thủ công theo vùng. */
class NotificationController extends Controller
{
    public const PER_PAGE = 30;

    public function index(Request $request, LookupService $lookup, DashboardService $dashboard): Response
    {
        $filters = $this->filters($request, ['status', 'channel', 'level']);
        $provinceId = $request->user()->scopedProvinceId();
        $base = Notification::query()->when($provinceId, fn ($q, $p) => $q->where(fn ($w) => $w
            ->whereHas('trip', fn ($t) => $t->inProvince($p))->orWhere('user_id', $request->user()->id)));
        $page = (clone $base)->with('deliveries', 'user:id,name', 'alert.translations', 'trip')
            ->when($filters['status'], fn ($q, $v) => $q->where('status', $v))
            ->when($filters['channel'], fn ($q, $v) => $q->where('channel', $v))
            ->when($filters['level'], fn ($q, $v) => $q->where('level', (int) $v))
            ->latest('id')->paginate(self::PER_PAGE)->withQueryString();
        $byStatus = (clone $base)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $ok = (int) ($byStatus['sent'] ?? 0) + (int) ($byStatus['read'] ?? 0);
        $failed = (int) ($byStatus['failed'] ?? 0);

        return Inertia::render('Admin/Notifications/Index', [
            'notifications' => NotificationItem::paginate($page),
            'stats' => [
                'by_status' => collect(Notification::STATUSES)->mapWithKeys(fn ($s) => [$s => (int) ($byStatus[$s] ?? 0)])->all(),
                'success_rate' => $ok + $failed > 0 ? round($ok / ($ok + $failed), 3) : null,
                'queue' => $dashboard->queueHealth(),
            ],
            'filters' => $filters,
            'zones' => $lookup->zones(),
            'channels' => $this->enumOptions('channels', Notification::CHANNELS),
        ]);
    }

    public function resend(Request $request, NotificationService $service, Notification $notification): RedirectResponse
    {
        $this->authorize('resend', $notification);
        $service->resend($notification, $request->user());

        return back()->with('success', __('messages.notifications.requeued'));
    }

    public function broadcast(BroadcastRequest $request, NotificationService $service, MatchingService $matching): RedirectResponse
    {
        $data = $request->validated();
        $zones = Zone::query()->whereIn('code', $data['zone_codes'])->get();
        $user = $request->user();
        if ($user->scopedProvinceId() && $zones->contains(fn (Zone $z) => (int) $z->province_id !== (int) $user->province_id)) {
            abort(403);
        }
        $result = $service->broadcast($matching->withDescendants($zones->pluck('id')->all()), (int) $data['level'], $data['title'],
            $data['body'], $data['channels'], $user);

        return back()->with('success', __('messages.notifications.broadcast', $result));
    }
}
