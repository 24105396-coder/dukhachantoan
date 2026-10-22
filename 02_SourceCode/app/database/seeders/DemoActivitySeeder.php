<?php

namespace Database\Seeders;

use App\Models\Artifact;
use App\Models\AuditLog;
use App\Models\Booking;
use App\Models\Contribution;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Payment;
use App\Models\Tag;
use App\Models\TourSlot;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Hoạt động mẫu (đặt lịch, thanh toán, đóng góp, lượt xem, nhật ký) để bảng điều khiển có số liệu.
 * Chỉ chạy một lần: nhận diện bằng ghi chú bắt đầu "[demo]".
 */
class DemoActivitySeeder extends Seeder
{
    private const BOOKING_STATUSES = ['held', 'pending_payment', 'paid', 'paid', 'paid', 'cancelled', 'expired', 'refunded'];

    public function run(): void
    {
        if (Booking::where('note', 'like', '[demo]%')->exists()) {
            return;
        }
        $members = User::whereHas('role', fn ($q) => $q->where('code', 'member'))->orderBy('id')->get();
        if ($members->isEmpty()) {
            return;
        }
        $this->seedBookings($members);
        $this->seedContributions($members);
        $this->seedPageViews($members);
        $this->seedAudit();
    }

    private function seedBookings($members): void
    {
        $slots = TourSlot::with('route')->where('status', 'open')->where('starts_at', '>', now())->orderBy('starts_at')->get();
        if ($slots->isEmpty()) {
            return;
        }
        foreach (self::BOOKING_STATUSES as $i => $status) {
            $slot = $slots[($i * 3) % $slots->count()];
            $user = $members[$i % $members->count()];
            $qty = 1 + $i % 3;
            $price = (int) ($slot->route->price_vnd ?? 0);
            if ($slot->capacity - $slot->booked_count < $qty) {
                continue;
            }
            DB::transaction(function () use ($slot, $user, $qty, $price, $status, $i) {
                $booking = Booking::create([
                    'code' => 'BK-'.Str::upper(Str::random(8)),
                    'slot_id' => $slot->id,
                    'user_id' => $user->id,
                    'quantity' => $qty,
                    'unit_price_vnd' => $price,
                    'total_vnd' => $price * $qty,
                    'status' => $status,
                    'hold_expires_at' => match ($status) {
                        'held', 'pending_payment' => now()->addMinutes((int) config('disanso.booking.hold_minutes', 10)),
                        'expired' => now()->subHours(2),
                        default => null,
                    },
                    'contact_name' => $user->name,
                    'contact_phone' => $user->phone ?: '0901234567',
                    'contact_email' => $user->email,
                    'note' => '[demo] Đặt lịch mẫu #'.($i + 1),
                    'cancelled_at' => $status === 'cancelled' ? now()->subDay() : null,
                    'cancel_reason' => $status === 'cancelled' ? 'Khách đổi kế hoạch' : null,
                ]);
                if ($booking->occupiesCapacity()) {
                    $slot->increment('booked_count', $qty);
                }
                $this->seedPayment($booking, $status);
            });
        }
    }

    private function seedPayment(Booking $booking, string $status): void
    {
        $paymentStatus = match ($status) {
            'paid' => 'succeeded',
            'pending_payment' => 'initiated',
            'refunded' => 'refunded',
            default => null,
        };
        if (! $paymentStatus) {
            return;
        }
        $txnRef = 'SB-'.now()->format('Ymd').'-'.Str::upper(Str::random(10));
        $payload = ['txn_ref' => $txnRef, 'amount' => $booking->total_vnd, 'outcome' => $paymentStatus === 'initiated' ? null : 'success', 'demo' => true];
        Payment::create([
            'booking_id' => $booking->id,
            'gateway' => 'sandbox',
            'txn_ref' => $txnRef,
            'amount_vnd' => $booking->total_vnd,
            'currency' => 'VND',
            'status' => $paymentStatus,
            'gateway_payload' => $payload,
            'signature' => hash_hmac('sha256', json_encode($payload), (string) config('disanso.payment.sandbox_secret', 'sandbox')),
            'paid_at' => $paymentStatus === 'initiated' ? null : now()->subDays(2),
            'refunded_at' => $paymentStatus === 'refunded' ? now()->subDay() : null,
        ]);
    }

    private function seedContributions($members): void
    {
        $sites = HeritageSite::published()->inRandomOrder()->limit(6)->pluck('id')->all();
        $tags = Tag::with('translations')->limit(10)->get();
        $samples = [
            ['story', 'pending', 'Kỷ niệm chuyến tham quan cùng gia đình', 'Chúng tôi đến đây vào dịp lễ hội, được nghe thuyết minh viên kể về lịch sử xây dựng và các lần trùng tu.'],
            ['correction', 'approved', 'Đề nghị cập nhật giờ mở cửa', 'Theo thông báo mới của ban quản lý, giờ mở cửa mùa hè kéo dài thêm 30 phút vào cuối tuần.'],
            ['media', 'rejected', 'Gửi ảnh chụp cổng chính', 'Ảnh chụp bằng điện thoại, tuy nhiên chưa ghi rõ thời gian và giấy phép sử dụng.'],
            ['story', 'needs_revision', 'Truyền thuyết địa phương', 'Người cao tuổi trong làng kể lại truyền thuyết về vị thần được thờ, cần bổ sung nguồn tham khảo.'],
            ['new_site', 'pending', 'Đề xuất bổ sung đình làng', 'Đình làng có kiến trúc gỗ thế kỷ 19, được xếp hạng cấp tỉnh, chưa có trên nền tảng.'],
            ['correction', 'approved', 'Sửa năm xây dựng', 'Năm xây dựng ghi trên bia đá ở sân là năm Cảnh Hưng thứ 10, khác với thông tin hiện tại.'],
        ];
        $staffId = User::where('email', 'manager@disanso.test')->value('id');
        foreach ($samples as $i => [$type, $status, $title, $body]) {
            $subjectId = $type === 'new_site' ? null : ($sites[$i % max(1, count($sites))] ?? null);
            $suggested = $tags->shuffle()->take(3)->values()->map(fn ($t, $k) => [
                'slug' => $t->slug, 'name' => $t->translate('vi')?->name ?? $t->slug, 'score' => round(0.8 - $k * 0.15, 2),
            ])->all();
            Contribution::create([
                'user_id' => $members[$i % $members->count()]->id,
                'subject_type' => $subjectId ? 'site' : null,
                'subject_id' => $subjectId,
                'contribution_type' => $type,
                'locale' => 'vi',
                'title' => $title,
                'body' => $body,
                'suggested_tags' => $suggested,
                'status' => $status,
                'reviewed_by' => $status === 'pending' ? null : $staffId,
                'review_note' => match ($status) {
                    'approved' => 'Đã xác minh và cập nhật.',
                    'rejected' => 'Thiếu thông tin bản quyền ảnh.',
                    'needs_revision' => 'Vui lòng bổ sung nguồn.',
                    default => null,
                },
                'reviewed_at' => $status === 'pending' ? null : now()->subDays(1),
            ]);
        }
    }

    private function seedPageViews($members): void
    {
        $subjects = collect()
            ->concat(HeritageSite::published()->pluck('id')->map(fn ($id) => ['site', $id, HeritageSite::class]))
            ->concat(Artifact::published()->pluck('id')->map(fn ($id) => ['artifact', $id, Artifact::class]))
            ->concat(HistoricalFigure::published()->pluck('id')->map(fn ($id) => ['figure', $id, HistoricalFigure::class]));
        if ($subjects->isEmpty()) {
            return;
        }
        $rows = [];
        $counts = [];
        for ($i = 0; $i < 300; $i++) {
            // Ưu tiên di sản: lấy mẫu lệch về đầu danh sách.
            [$type, $id, $class] = $subjects[(int) floor((mt_rand() / mt_getrandmax()) ** 2 * $subjects->count())];
            $at = now()->subDays(mt_rand(0, 29))->setTime(mt_rand(7, 22), mt_rand(0, 59));
            $rows[] = [
                'subject_type' => $type,
                'subject_id' => $id,
                'user_id' => mt_rand(0, 3) === 0 ? $members->random()->id : null,
                'locale' => mt_rand(0, 3) === 0 ? 'en' : 'vi',
                'viewed_on' => $at->toDateString(),
                'created_at' => $at,
            ];
            $counts[$class][$id] = ($counts[$class][$id] ?? 0) + 1;
        }
        DB::table('page_views')->insert($rows);
        foreach ($counts as $class => $byId) {
            foreach ($byId as $id => $n) {
                $class::whereKey($id)->increment('view_count', $n);
            }
        }
    }

    private function seedAudit(): void
    {
        $admin = User::where('email', 'admin@disanso.test')->first();
        $manager = User::where('email', 'manager@disanso.test')->first();
        $site = HeritageSite::first();
        $paid = Booking::where('status', 'paid')->first();
        $entries = [
            [$admin?->id, 'auth.login', null, null, ['email' => $admin?->email]],
            [$manager?->id, 'auth.login', null, null, ['email' => $manager?->email]],
            [$manager?->id, 'admin.site.update', $site ? 'site' : null, $site?->id, ['fields' => ['visiting_hours']]],
            [$paid?->user_id, 'booking.paid', $paid ? 'booking' : null, $paid?->id, ['code' => $paid?->code, 'amount_vnd' => $paid?->total_vnd]],
            [$admin?->id, 'admin.user.update', 'user', $manager?->id, ['is_active' => true]],
        ];
        foreach ($entries as $k => [$userId, $action, $type, $id, $meta]) {
            AuditLog::create([
                'user_id' => $userId,
                'action' => $action,
                'subject_type' => $type,
                'subject_id' => $id,
                'ip' => '127.0.0.1',
                'user_agent' => 'DiSanSo seeder',
                'meta' => $meta,
                'created_at' => now()->subHours(10 - $k),
            ]);
        }
    }
}
