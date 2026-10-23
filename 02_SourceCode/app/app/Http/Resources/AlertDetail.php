<?php

namespace App\Http\Resources;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\EmergencyContact;
use App\Models\ResponseGuide;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * AlertDetail = AlertCard + hướng dẫn, danh bạ, lịch sử trạng thái.
 * affected_trips_count chỉ có khi gọi withAffectedTrips() (agency/admin).
 *
 * @mixin Alert
 */
class AlertDetail extends BaseResource
{
    public const MAX_GUIDES = 4;
    public const MAX_CONTACTS = 8;
    public const RAW_EXCERPT_LENGTH = 600;

    private ?int $affectedTrips = null;

    public function withAffectedTrips(int $count): static
    {
        $this->affectedTrips = $count;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $alert = $this->resource;
        $data = (new AlertCard($alert))->toArray($request) + [
            'raw_payload_excerpt' => $alert->raw_payload ? Str::limit(json_encode($alert->raw_payload, JSON_UNESCAPED_UNICODE), self::RAW_EXCERPT_LENGTH) : null,
            'translations_available' => $alert->availableLocales(),
            'guides' => GuideCard::list($this->guides($alert)),
            'contacts' => ContactItem::list($this->contacts($alert)),
            'history' => $this->history($alert),
        ];
        if ($this->affectedTrips !== null) {
            $data['affected_trips_count'] = $this->affectedTrips;
        }

        return $data;
    }

    private function guides(Alert $alert)
    {
        return ResponseGuide::query()->published()->with('translations', 'hazardType.translations')
            ->where('hazard_type_id', $alert->hazard_type_id)
            ->where('min_severity', '<=', max(1, (int) $alert->severity))
            ->orderBy('sort_order')->limit(self::MAX_GUIDES)->get();
    }

    /** Danh bạ của các tỉnh trong vùng ảnh hưởng, ưu tiên số khẩn cấp; thiếu thì lấy số toàn quốc. */
    private function contacts(Alert $alert)
    {
        $provinceIds = $alert->zones->pluck('province_id')->filter()->unique()->values();

        return EmergencyContact::query()->with('translations', 'province', 'zone')
            ->where(fn ($q) => $q->whereIn('province_id', $provinceIds)->orWhereNull('province_id'))
            ->whereIn('contact_type', ['police', 'fire', 'ambulance', 'rescue', 'coast_guard', 'tourist_support', 'hotline_national'])
            ->orderByRaw('province_id IS NULL')->orderBy('sort_order')->limit(self::MAX_CONTACTS)->get();
    }

    /** Lịch sử: tạo/phát hành/cập nhật/hết hạn/hủy theo nhật ký kiểm toán, dự phòng các mốc trên bản ghi. */
    private function history(Alert $alert): array
    {
        $logs = AuditLog::query()->with('user:id,name')
            ->where('subject_type', 'alert')->where('subject_id', $alert->id)
            ->where('action', 'like', 'alert.%')->orderBy('created_at')->limit(50)->get();
        if ($logs->isNotEmpty()) {
            return $logs->map(fn (AuditLog $log) => [
                'status' => $log->meta['status'] ?? str_replace('alert.', '', $log->action),
                'action' => $log->action,
                'at' => self::iso($log->created_at),
                'by' => $log->user ? ['name' => $log->user->name] : null,
            ])->values()->all();
        }

        return collect([
            // Dữ liệu nạp sẵn có created_at (lúc seed) muộn hơn published_at: lấy mốc sớm hơn cho bước nháp
            ['status' => Alert::STATUS_DRAFT, 'at' => $alert->published_at && $alert->created_at?->gt($alert->published_at)
                ? $alert->published_at : $alert->created_at],
            ['status' => Alert::STATUS_ACTIVE, 'at' => $alert->published_at],
            ['status' => $alert->status, 'at' => $alert->expired_at ?? ($alert->status === Alert::STATUS_UPDATED ? $alert->updated_at : null)],
        ])->filter(fn ($row) => $row['at'] !== null)->unique('status')
            ->map(fn ($row) => ['status' => $row['status'], 'action' => null, 'at' => self::iso($row['at']), 'by' => null])
            ->values()->all();
    }
}
