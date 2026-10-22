<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BookingStatusRequest;
use App\Http\Resources\BookingItemResource;
use App\Models\Booking;
use App\Models\TourRoute;
use App\Services\BookingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $filters = $this->filters($request, ['status', 'route', 'from', 'to', 'q']);

        $bookings = self::query($filters, $user->isStaff() ? null : $user->id)
            ->with(array_merge(BookingItemResource::eager(), ['user']))
            ->latest('id')->paginate(20)->withQueryString();

        $routes = TourRoute::query()->with('translations')
            ->when(! $user->isStaff(), fn ($q) => $q->where('created_by', $user->id))
            ->orderBy('slug')->get()->map(fn ($r) => ['slug' => $r->slug, 'name' => $r->name])->all();

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => BookingItemResource::paginate($bookings),
            'filters' => $filters,
            'routes' => $routes,
            'statuses' => HeritagePresenter::enumList('booking_statuses', array_keys(Booking::TRANSITIONS)),
            'transitions' => Booking::TRANSITIONS,
            'canChangeStatus' => $user->isStaff(),
        ]);
    }

    /** Lọc dùng chung cho danh sách và xuất CSV; $ownerId giới hạn theo tuyến của contributor. */
    public static function query(array $f, ?int $ownerId = null): Builder
    {
        return Booking::query()
            ->when($ownerId, fn ($q) => $q->whereHas('slot.route', fn ($r) => $r->withTrashed()->where('created_by', $ownerId)))
            ->when($f['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($f['route'] ?? null, fn ($q, $v) => $q->whereHas('slot.route', fn ($r) => $r->where('slug', $v)))
            ->when(self::date($f['from'] ?? null), fn ($q, $v) => $q->where('created_at', '>=', $v.' 00:00:00'))
            ->when(self::date($f['to'] ?? null), fn ($q, $v) => $q->where('created_at', '<=', $v.' 23:59:59'))
            ->when($f['q'] ?? null, function ($q, $v) {
                $like = like_contains($v);
                $q->where(fn ($w) => $w->where('code', 'like', $like)->orWhere('contact_name', 'like', $like)->orWhere('contact_email', 'like', $like));
            });
    }

    public function status(BookingStatusRequest $request, Booking $booking, BookingService $service): RedirectResponse
    {
        $this->authorize('updateStatus', $booking);
        $target = $request->validated('status');
        try {
            $target === Booking::REFUNDED
                ? $service->refund($booking, $request->user(), $request->validated('reason'))
                : $service->transition($booking, $target, $request->user(), $request->validated('reason'));
        } catch (BookingException $e) {
            return back()->withErrors(['status' => $e->getMessage()])->with('error', $e->getMessage());
        }

        return back()->with('success', __('messages.booking.status_updated'));
    }

    private static function date(?string $value): ?string
    {
        return $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
