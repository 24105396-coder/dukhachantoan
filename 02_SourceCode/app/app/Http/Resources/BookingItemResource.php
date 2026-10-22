<?php

namespace App\Http\Resources;

use App\Services\BookingService;
use App\Support\UrlToken;
use Illuminate\Http\Request;

class BookingItemResource extends BaseResource
{
    public const URL_TOKEN_PURPOSE = 'booking';

    public static function eager(): array
    {
        return ['slot.route' => fn ($q) => $q->withTrashed(), 'slot.route.translations', 'payments'];
    }

    public function toArray(Request $request): array
    {
        $service = app(BookingService::class);
        $slot = $this->slot;
        $route = $slot?->route;
        $secondsLeft = $this->hold_expires_at && in_array($this->status, ['held', 'pending_payment'], true)
            ? max(0, now()->diffInSeconds($this->hold_expires_at, false)) : null;

        $data = [
            'id' => $this->id,
            'code' => $this->code,
            'status' => $this->status,
            'status_label' => __('enums.booking_statuses.'.$this->status),
            'quantity' => (int) $this->quantity,
            'unit_price_vnd' => (int) $this->unit_price_vnd,
            'total_vnd' => (int) $this->total_vnd,
            'hold_expires_at' => $this->hold_expires_at?->toIso8601String(),
            'seconds_left' => $secondsLeft === null ? null : (int) $secondsLeft,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'contact_email' => $this->contact_email,
            'note' => $this->note,
            'cancel_reason' => $this->cancel_reason,
            'created_at' => $this->created_at?->toIso8601String(),
            'slot' => $slot ? (SlotItemResource::one($slot) + [
                'route' => $route ? [
                    'slug' => $route->slug,
                    'name' => $route->name,
                    'url' => localized_route('tours.show', ['route' => $route->slug]),
                ] : null,
            ]) : null,
            // txn_ref đủ để gửi IPN giả → chỉ chủ booking và staff thấy giao dịch (contributor thì không)
            'payments' => ! $this->canSeePayments($request) ? [] : $this->payments->sortByDesc('id')->map(fn ($p) => [
                'txn_ref' => $p->txn_ref,
                'status' => $p->status,
                'amount_vnd' => (int) $p->amount_vnd,
                'paid_at' => $p->paid_at?->toIso8601String(),
                'gateway' => $p->gateway,
            ])->values()->all(),
            'can_pay' => $service->canPay($this->resource),
            'can_cancel' => $service->canCancel($this->resource),
            'url' => localized_route('bookings.show', ['booking' => $this->code]),
            // Liên kết tra cứu dùng trong email/trang xác nhận: id mã hóa, không lộ id tự tăng
            'lookup_url' => localized_route('bookings.lookup', ['token' => UrlToken::encode(self::URL_TOKEN_PURPOSE, (int) $this->id)]),
        ];

        if ($this->relationLoaded('user') && $request->user()?->id !== $this->user_id) {
            $data['user'] = ['name' => $this->user?->name, 'email' => $this->user?->email];
        }

        return $data;
    }

    private function canSeePayments(Request $request): bool
    {
        $viewer = $request->user();

        return $viewer !== null && ($viewer->isStaff() || (int) $viewer->id === (int) $this->user_id);
    }
}
