<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/** Cơ sở cho API mở: locale qua ?lang=vi|en (page-contract) hoặc ?locale=, phân trang {data, meta, links}. */
abstract class ApiController extends Controller
{
    public const DEFAULT_LOCALE = 'vi';

    protected function applyLocale(Request $request): string
    {
        $wanted = $request->query('lang', $request->query('locale'));
        // Không dùng config('app.locale') làm mặc định: setLocale() ghi đè giá trị đó trong cùng tiến trình
        $locale = in_array($wanted, ['vi', 'en'], true) ? $wanted : self::DEFAULT_LOCALE;
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        return $locale;
    }

    protected function perPage(Request $request): int
    {
        return max(1, min(50, (int) $request->query('per_page', 20)));
    }

    protected function paginated(LengthAwarePaginator $page, string $resource): JsonResponse
    {
        return response()->json([
            'data' => $resource::list($page->items()),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'locale' => app()->getLocale(),
            ],
            'links' => [
                'first' => $page->url(1),
                'last' => $page->url($page->lastPage()),
                'prev' => $page->previousPageUrl(),
                'next' => $page->nextPageUrl(),
            ],
        ]);
    }
}
