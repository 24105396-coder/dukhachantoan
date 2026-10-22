<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertCard;
use App\Http\Resources\ContactItem;
use App\Http\Resources\GuideCard;
use App\Http\Resources\GuideDetail;
use App\Models\Alert;
use App\Models\EmergencyContact;
use App\Models\ResponseGuide;
use App\Services\LookupService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Hướng dẫn ứng phó đa ngữ (F7): nhóm theo loại rủi ro; chi tiết kèm danh bạ và cảnh báo liên quan. */
class GuideController extends Controller
{
    public const RELATED_ALERTS = 6;

    public function index(Request $request, LookupService $lookup): Response
    {
        $filters = $this->filters($request, ['hazard', 'audience']);
        $guides = ResponseGuide::query()->published()->with(GuideCard::RELATIONS)
            ->when($filters['hazard'], fn ($q, $v) => $q->whereHas('hazardType', fn ($h) => $h->where('code', $v)))
            ->when($filters['audience'], fn ($q, $v) => $q->where('audience', $v))
            ->get()->sortBy(fn ($g) => [$g->hazardType?->sort_order ?? 999, $g->sort_order]);

        return Inertia::render('Guides/Index', [
            'guides' => $guides->groupBy('hazard_type_id')->map(fn ($items) => [
                'hazard' => $items->first()->hazardType?->brief(),
                'guides' => GuideCard::list($items),
            ])->values()->all(),
            'hazards' => $lookup->hazards(),
            'filters' => $filters,
        ]);
    }

    public function show(Request $request, ResponseGuide $guide): Response
    {
        abort_unless($guide->status === 'published', 404);
        $guide->load(GuideCard::RELATIONS);
        $provinceId = $request->user()?->province_id;

        return Inertia::render('Guides/Show', [
            'guide' => GuideDetail::one($guide),
            'contacts' => ContactItem::list(EmergencyContact::query()->with(ContactItem::RELATIONS)
                ->when($provinceId, fn ($q) => $q->where(fn ($w) => $w->where('province_id', $provinceId)->orWhereNull('province_id')),
                    fn ($q) => $q->whereNull('province_id'))
                ->orderByRaw('province_id IS NULL')->orderBy('sort_order')->limit(12)->get()),
            'relatedAlerts' => AlertCard::list(Alert::query()->live()->where('hazard_type_id', $guide->hazard_type_id)
                ->with(AlertCard::RELATIONS)->mostSevere()->limit(self::RELATED_ALERTS)->get()),
        ]);
    }
}
