<?php

namespace Database\Seeders;

use App\Models\HazardType;
use App\Models\IncidentReport;
use App\Models\SafetyCheckin;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\Concerns\ReadsSeedJson;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu demo trang "Yêu cầu của tôi" cho traveler@dukhach.test: 3 sự cố (chờ duyệt / đã xác minh / không xác minh được)
 * và 3 check-in "cần hỗ trợ" (đã gửi / đang xử lý / đã đóng). Nội dung là mô phỏng (is_simulated = 1 cho sự cố).
 * Idempotent: sự cố theo (user_id, title), check-in theo (trip_id, message); chạy lại cập nhật thời điểm tương đối.
 * Thiếu tài khoản/vùng/hành trình thì bỏ qua mục đó.
 */
class RequestDemoSeeder extends Seeder
{
    use ReadsSeedJson;

    public const TRAVELER = 'traveler@dukhach.test';
    public const AGENCY = 'agency@dukhach.test';
    public const ADMIN = 'admin@dukhach.test';

    /** [vùng, loại rủi ro, tiêu đề, mô tả, trạng thái, người duyệt, ghi chú duyệt, số phút trước]. */
    private const INCIDENTS = [
        ['quang-ninh', 'giao_thong', 'Cây đổ chắn đường lên khu Bãi Cháy',
            'Một cây lớn đổ ngang đường sau cơn dông, xe máy và ô tô phải quay đầu.', 'pending', null, null, 35],
        ['khanh-hoa', 'song_lon', 'Sóng lớn tràn bờ kè đường Trần Phú',
            'Sóng đánh qua kè, nước tràn lên vỉa hè đoạn gần tháp Trầm Hương.', 'verified', self::AGENCY,
            'Đã xác minh với Ban quản lý bờ biển; khuyến cáo không xuống tắm biển trong chiều nay.', 300],
        ['lao-cai', 'sat_lo', 'Nghi sạt lở taluy dương gần bản Cát Cát',
            'Thấy đất đá lăn xuống mép đường, chưa rõ quy mô.', 'rejected', self::ADMIN,
            'Lực lượng địa phương kiểm tra hiện trường, không phát hiện sạt lở; đất đá do thi công rơi vãi đã được dọn.', 1500],
    ];

    /** [mã hành trình, lời nhắn, trạng thái xử lý, người phản hồi, ghi chú phản hồi, số phút trước]. */
    private const CHECKINS = [
        ['TR-SEED-04', 'Đường xuống thác Bạc trơn trượt, một người trong đoàn bị trật chân, cần hướng dẫn tới trạm y tế gần nhất.',
            'open', null, null, 40],
        ['TR-SEED-02', 'Nước sông dâng cao gần bến đò Châu Đốc, đoàn chưa rõ có nên tiếp tục đi thuyền không.',
            'in_progress', self::ADMIN, 'Đã liên hệ Trung tâm hỗ trợ du khách tỉnh, đang xác nhận lịch chạy tàu.', 180],
        ['TR-SEED-04', 'Mất liên lạc với một thành viên khi leo Fansipan, đã thấy lại sau 30 phút.',
            'closed', self::ADMIN, 'Đã liên lạc được với trưởng đoàn, mọi thành viên an toàn. Đóng yêu cầu.', 2880],
    ];

    public function run(): void
    {
        $users = User::query()->whereIn('email', [self::TRAVELER, self::AGENCY, self::ADMIN])->pluck('id', 'email');
        if (! isset($users[self::TRAVELER])) {
            $this->warnSeed('yêu cầu mẫu: bỏ qua, chưa có tài khoản '.self::TRAVELER);

            return;
        }
        $this->seedIncidents((int) $users[self::TRAVELER], $users->all());
        $this->seedCheckins((int) $users[self::TRAVELER], $users->all());
    }

    private function seedIncidents(int $travelerId, array $users): void
    {
        $zones = Zone::query()->whereIn('code', array_column(self::INCIDENTS, 0))->get()->keyBy('code');
        $hazards = HazardType::query()->whereIn('code', array_column(self::INCIDENTS, 1))->pluck('id', 'code');
        foreach (self::INCIDENTS as [$zone, $hazard, $title, $description, $status, $reviewer, $note, $minutes]) {
            if (! isset($zones[$zone], $hazards[$hazard])) {
                $this->warnSeed("yêu cầu mẫu: bỏ qua sự cố \"{$title}\" (thiếu vùng {$zone} hoặc loại {$hazard})");

                continue;
            }
            $created = now()->subMinutes($minutes);
            $incident = IncidentReport::updateOrCreate(['user_id' => $travelerId, 'title' => $title], [
                'zone_id' => $zones[$zone]->id, 'hazard_type_id' => $hazards[$hazard], 'description' => $description,
                'lat' => $zones[$zone]->centroid_lat, 'lng' => $zones[$zone]->centroid_lng, 'status' => $status,
                'reviewed_by' => $reviewer ? ($users[$reviewer] ?? null) : null, 'review_note' => $note,
                'reviewed_at' => $reviewer ? $created->copy()->addMinutes(20) : null, 'is_simulated' => true,
            ]);
            $incident->forceFill(['created_at' => $created])->saveQuietly();
        }
    }

    private function seedCheckins(int $travelerId, array $users): void
    {
        $trips = Trip::query()->whereIn('code', array_column(self::CHECKINS, 0))->with('legs')->get()->keyBy('code');
        foreach (self::CHECKINS as [$code, $message, $status, $responder, $note, $minutes]) {
            if (! isset($trips[$code])) {
                $this->warnSeed("yêu cầu mẫu: bỏ qua check-in, chưa có hành trình {$code}");

                continue;
            }
            $checked = now()->subMinutes($minutes);
            $leg = $trips[$code]->legs->first();
            SafetyCheckin::updateOrCreate(['trip_id' => $trips[$code]->id, 'message' => $message], [
                'user_id' => $travelerId, 'status' => SafetyCheckin::STATUS_NEED_HELP, 'lat' => $leg?->lat, 'lng' => $leg?->lng,
                'accuracy_m' => $leg?->lat !== null ? 30 : null, 'locale' => 'vi', 'source' => 'app', 'checked_at' => $checked,
                'response_status' => $status, 'responded_by' => $responder ? ($users[$responder] ?? null) : null,
                'responded_at' => $responder ? $checked->copy()->addMinutes(15) : null, 'response_note' => $note,
            ]);
        }
    }
}
