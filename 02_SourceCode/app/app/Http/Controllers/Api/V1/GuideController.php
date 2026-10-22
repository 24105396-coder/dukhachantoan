<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\GuideCard;
use App\Http\Resources\GuideDetail;
use App\Models\ResponseGuide;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** API hướng dẫn ứng phó (chỉ bản đã xuất bản). */
class GuideController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->applyLocale($request);
        $hazard = $this->filters($request, ['hazard'])['hazard'];
        $guides = ResponseGuide::query()->published()->with(GuideCard::RELATIONS)
            ->when($hazard, fn ($q, $v) => $q->whereHas('hazardType', fn ($h) => $h->where('code', $v)))
            ->orderBy('hazard_type_id')->orderBy('sort_order')->get();

        return response()->json(['data' => GuideCard::list($guides)]);
    }

    public function show(Request $request, string $guide): JsonResponse
    {
        $this->applyLocale($request);
        $model = ResponseGuide::query()->published()->where('slug', $guide)->with(GuideCard::RELATIONS)->firstOrFail();

        return response()->json(['data' => GuideDetail::one($model)]);
    }
}
