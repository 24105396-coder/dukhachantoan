<?php

namespace App\Http\Controllers;

use App\Http\Resources\TimelineItemResource;
use App\Models\TimelineEvent;
use App\Services\LookupService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TimelineController extends Controller
{
    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['period', 'type', 'q']);

        return Inertia::render('Timeline/Index', [
            'events' => TimelineItemResource::list(self::query($filters)->get()),
            'periods' => $lookup->periods(),
            'types' => $this->enumOptions('event_types', ['xay_dung', 'trung_tu', 'cong_nhan', 'su_kien', 'sinh', 'mat', 'phat_hien', 'khac']),
            'filters' => $filters,
        ]);
    }

    /** Sự kiện thuộc di sản/nhân vật đã công bố, sắp theo năm. Dùng chung cho API. */
    public static function query(array $f): Builder
    {
        $query = TimelineEvent::query()->with(TimelineItemResource::eager())
            ->where(fn ($q) => $q->whereHas('site', fn ($s) => $s->published())
                ->orWhereHas('figure', fn ($s) => $s->published()))
            ->when($f['period'] ?? null, fn ($q, $v) => $q->whereHas('period', fn ($p) => $p->where('code', $v)))
            ->when($f['type'] ?? null, fn ($q, $v) => $q->where('event_type', $v))
            ->when(isset($f['from']) && is_numeric($f['from']), fn ($q) => $q->where('event_year', '>=', (int) $f['from']))
            ->when(isset($f['to']) && is_numeric($f['to']), fn ($q) => $q->where('event_year', '<=', (int) $f['to']));

        if (! empty($f['q'])) {
            $like = like_contains($f['q']);
            $query->whereHas('translations', fn ($t) => $t->where('title', 'like', $like)->orWhere('description', 'like', $like));
        }

        return $query->orderBy('event_year')->orderBy('event_month')->orderBy('id');
    }
}
