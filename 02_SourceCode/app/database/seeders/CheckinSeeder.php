<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/**
 * Check-in an toàn mẫu để dashboard có số liệu ngay sau khi nạp dữ liệu: đa số "an toàn", 3 "cần hỗ trợ"
 * đang mở ở hành trình đi qua tỉnh Khánh Hòa (tài khoản agency mẫu), 1 "chưa rõ".
 * Idempotent theo (trip_id, message): chạy lại chỉ cập nhật thời điểm tương đối và đặt lại trạng thái phản hồi.
 * Hành trình không tồn tại thì bỏ qua. Nội dung là mô phỏng.
 */
class CheckinSeeder extends Seeder
{
    use ReadsSeedJson;

    /** Tỉnh có 3 check-in cần hỗ trợ (trùng tỉnh của agency@dukhach.test). */
    public const NEED_HELP_PROVINCE = 'KH';

    /** [mã hành trình, trạng thái, số phút trước, nguồn, lời nhắn]. */
    private const SAMPLES = [
        ['TR-SEED-01', 'safe', 45, 'app', 'Cả đoàn đã về khách sạn ở Hạ Long an toàn.'],
        ['TR-SEED-06', 'safe', 90, 'app', 'Đang ở Đà Nẵng, trời mưa nhỏ, mọi người ổn.'],
        ['TR-SEED-10', 'safe', 150, 'share_link', 'Người thân báo: đoàn đã xuống núi Fansipan an toàn.'],
        ['TR-SEED-16', 'safe', 200, 'app', 'Đã tới Đông Hà, đường thông thoáng.'],
        ['TR-SEED-18', 'safe', 260, 'app', 'Ở trung tâm TP.HCM, không ảnh hưởng.'],
        ['TR-SEED-22', 'safe', 320, 'app', 'Cả nhà an toàn tại Hà Nội.'],
        ['TR-SEED-30', 'safe', 380, 'app', 'Đoàn 12 khách đã nhận phòng ở Sa Pa an toàn.'],
        ['TR-SEED-31', 'safe', 500, 'app', 'Đoàn đã rời Huế, đang di chuyển vào Đà Nẵng.'],
        ['TR-SEED-28', 'unknown', 120, 'agency', 'Chưa liên lạc được với trưởng đoàn sau khi qua đèo, đang gọi lại.'],
    ];

    /** Cần hỗ trợ ở Khánh Hòa: [số phút trước, lời nhắn]. */
    private const NEED_HELP = [
        [25, 'Xe khách chết máy trên đèo Khánh Lê, nước suối dâng cao, cần hướng dẫn tìm chỗ trú.'],
        [55, 'Một thành viên bị say sóng nặng khi đi tàu ra đảo, cần hỗ trợ y tế ở bến.'],
        [95, 'Bị kẹt ở khu vực ngập tại Nha Trang, nước ngang gối, cần chỉ đường an toàn.'],
    ];

    public function run(): void
    {
        $trips = Trip::query()->whereIn('code', array_column(self::SAMPLES, 0))->with('legs')->get()->keyBy('code');
        foreach (self::SAMPLES as [$code, $status, $minutes, $source, $message]) {
            if (! isset($trips[$code])) {
                $this->warnSeed("check-in mẫu: bỏ qua, chưa có hành trình {$code}");

                continue;
            }
            $this->upsert($trips[$code], $status, $minutes, $source, $message);
        }
        $this->seedNeedHelp();
    }

    private function seedNeedHelp(): void
    {
        $provinceId = Province::query()->where('code', self::NEED_HELP_PROVINCE)->value('id');
        $trips = $provinceId ? Trip::query()->ongoing()->inProvince($provinceId)->with('legs.zone')->orderBy('code')->get() : collect();
        if ($trips->isEmpty()) {
            $this->warnSeed('check-in mẫu: không có hành trình đang diễn ra ở tỉnh '.self::NEED_HELP_PROVINCE);

            return;
        }
        foreach (self::NEED_HELP as $i => [$minutes, $message]) {
            $trip = $trips[$i % $trips->count()];
            $this->upsert($trip, SafetyCheckin::STATUS_NEED_HELP, $minutes, 'app', $message, $provinceId);
        }
    }

    private function upsert(Trip $trip, string $status, int $minutes, string $source, string $message, ?int $provinceId = null): void
    {
        $leg = $provinceId
            ? $trip->legs->first(fn ($l) => (int) $l->zone?->province_id === (int) $provinceId)
            : $trip->legs->first();
        SafetyCheckin::updateOrCreate(['trip_id' => $trip->id, 'message' => $message], [
            'user_id' => $source === 'app' ? $trip->user_id : null,
            'status' => $status,
            'lat' => $leg?->lat,
            'lng' => $leg?->lng,
            'accuracy_m' => $leg?->lat !== null ? 50 : null,
            'locale' => 'vi',
            'source' => $source,
            'checked_at' => now()->subMinutes($minutes),
            'responded_by' => null,
            'responded_at' => null,
            'response_note' => null,
            'response_status' => 'open',
        ]);
    }
}
