<?php

namespace App\Services;

use App\Http\Resources\RequestItem;
use App\Models\Alert;
use App\Models\IncidentReport;
use App\Models\SafetyCheckin;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Yêu cầu của tôi": sự cố người dùng đã báo + check-in "cần hỗ trợ" của chính họ hoặc của hành trình họ sở hữu,
 * kèm trạng thái xử lý của cơ quan. Không bao giờ trả bản ghi của người khác (kể cả khi người xem là cơ quan).
 */
class TravelerRequestService
{
    public const PER_PAGE = 20;
    public const INCIDENT_PAGE = 'incidents_page';
    public const CHECKIN_PAGE = 'checkins_page';
    private const HIGHLIGHT_PATTERN = '/^(incident|checkin):(\d{1,10})$/';

    /** Dữ liệu chung web + API: {incidents, checkins, counts, pagination}. */
    public function overview(User $user): array
    {
        $incidents = $this->incidentsFor($user)->with(RequestItem::INCIDENT_RELATIONS)->latest('id')
            ->paginate(self::PER_PAGE, ['*'], self::INCIDENT_PAGE)->withQueryString();
        $checkins = $this->checkinsFor($user)->with(RequestItem::CHECKIN_RELATIONS)->latest('checked_at')->latest('id')
            ->paginate(self::PER_PAGE, ['*'], self::CHECKIN_PAGE)->withQueryString();
        $this->attachDerivedAlerts($incidents->getCollection());

        return [
            'incidents' => RequestItem::list($incidents->items()),
            'checkins' => RequestItem::list($checkins->items()),
            'counts' => $this->counts($user),
            'pagination' => ['incidents' => self::pageMeta($incidents), 'checkins' => self::pageMeta($checkins)],
        ];
    }

    /** ?moi=incident:12 | checkin:5 → {type, id}; giá trị lạ → null. */
    public static function highlight(mixed $value): ?array
    {
        if (! is_string($value) || ! preg_match(self::HIGHLIGHT_PATTERN, $value, $m)) {
            return null;
        }

        return ['type' => $m[1], 'id' => (int) $m[2]];
    }

    /** {pending: đã gửi chờ tiếp nhận, in_progress: đang xử lý, done: đã xử lý/không xác minh được}. */
    public function counts(User $user): array
    {
        $incidents = $this->incidentsFor($user)->selectRaw('status, COUNT(*) AS total')->groupBy('status')->pluck('total', 'status');
        $checkins = $this->checkinsFor($user)->selectRaw('response_status, COUNT(*) AS total')->groupBy('response_status')
            ->pluck('total', 'response_status');

        return [
            'pending' => (int) ($incidents['pending'] ?? 0) + (int) ($checkins['open'] ?? 0),
            'in_progress' => (int) ($checkins['in_progress'] ?? 0),
            'done' => (int) ($incidents['verified'] ?? 0) + (int) ($incidents['rejected'] ?? 0) + (int) ($checkins['closed'] ?? 0),
        ];
    }

    public function incidentsFor(User $user): Builder
    {
        return IncidentReport::query()->where('user_id', $user->id);
    }

    /** Check-in cần hỗ trợ do chính người dùng gửi hoặc thuộc hành trình họ sở hữu/đăng ký cho đoàn. */
    public function checkinsFor(User $user): Builder
    {
        return SafetyCheckin::query()->where('status', SafetyCheckin::STATUS_NEED_HELP)
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereHas('trip', fn ($t) => $t->where('user_id', $user->id)->orWhere('partner_id', $user->id)));
    }

    /** Cảnh báo cơ quan tạo từ sự cố (raw_payload.incident_id) – một truy vấn cho cả trang. */
    private function attachDerivedAlerts($incidents): void
    {
        $ids = $incidents->pluck('id')->all();
        $alerts = $ids === [] ? collect() : Alert::query()->with('translations')->whereIn('raw_payload->incident_id', $ids)
            ->orderBy('id')->get()->keyBy(fn (Alert $a) => (int) ($a->raw_payload['incident_id'] ?? 0));
        $incidents->each(fn (IncidentReport $i) => $i->setRelation('derivedAlert', $alerts->get($i->id)));
    }

    private static function pageMeta(LengthAwarePaginator $page): array
    {
        return [
            'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(),
            'per_page' => $page->perPage(), 'total' => $page->total(),
        ];
    }
}
