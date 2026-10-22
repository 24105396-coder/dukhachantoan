<?php

namespace App\Http\Controllers;

use App\Http\Resources\AlertCard;
use App\Http\Resources\LegItem;
use App\Http\Resources\ZoneGeo;
use App\Models\Alert;
use App\Models\TripLeg;
use App\Models\Zone;
use App\Services\PageCache;
use App\Services\RiskService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Bản đồ vùng rủi ro (F5): vùng tô màu theo mức, cảnh báo có hình học, chặng của tôi khi đăng nhập. */
class MapController extends Controller
{
    public const CENTER = ['lat' => 16.0, 'lng' => 106.5, 'zoom' => 5];
    public const LEVEL_COLORS = [0 => '#94A3B8', 1 => '#38BDF8', 2 => '#FACC15', 3 => '#F97316', 4 => '#DC2626'];

    public function __invoke(Request $request, PageCache $cache): Response
    {
        return Inertia::render('Map/Index', [
            'zonesGeo' => $cache->remember('zones-geo', fn () => ZoneGeo::collection(Zone::query()->active()->orderBy('id')->get())),
            'alerts' => AlertCard::list(Alert::query()->live()->whereNotNull('geometry')->with(AlertCard::RELATIONS)->mostSevere()->get()),
            'legend' => collect(self::LEVEL_COLORS)->map(fn ($color, $level) => [
                'level' => $level, 'label' => RiskService::levelLabel($level), 'color' => $color,
            ])->values()->all(),
            'myLegs' => $request->user() ? LegItem::list($this->myLegs($request)) : [],
            'center' => self::CENTER,
        ]);
    }

    private function myLegs(Request $request)
    {
        $user = $request->user();

        return TripLeg::query()
            ->whereHas('trip', fn ($q) => $q->ongoing()->where(fn ($w) => $w->where('user_id', $user->id)->orWhere('partner_id', $user->id)))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->with(['zone.province', 'destination.translations', 'destination.zone', 'destination.province',
                'matches.alert' => fn ($q) => $q->with(AlertCard::RELATIONS)])
            ->orderBy('starts_at')->limit(50)->get();
    }
}
