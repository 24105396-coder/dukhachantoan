<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Http\Resources\BookingItemResource;
use App\Http\Resources\ContributionItemResource;
use App\Http\Resources\RouteCardResource;
use App\Models\Favorite;
use App\Models\Role;
use App\Models\TourRoute;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public const FAVORITE_ROUTES = ['site' => 'sites.show', 'artifact' => 'artifacts.show', 'figure' => 'figures.show'];

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $bookings = $user->bookings()->with(BookingItemResource::eager())->latest('id')->limit(10)->get();
        $contributions = $user->contributions()->with(ContributionItemResource::eager())->latest('id')->limit(10)->get();
        $ownRoutes = $user->hasRole(Role::CONTRIBUTOR, Role::ADMIN, Role::MANAGER)
            ? TourRoute::query()->where('created_by', $user->id)->with(RouteCardResource::eager())->withCount('stops')->get()
            : collect();

        return Inertia::render('Account/Dashboard', [
            'bookings' => BookingItemResource::list($bookings),
            'contributions' => ContributionItemResource::list($contributions),
            'favorites' => $this->favorites($user->id),
            'ownRoutes' => RouteCardResource::list($ownRoutes),
        ]);
    }

    /** [{type, slug, title, cover, url}] – bỏ qua đối tượng đã bị xóa/ẩn. */
    private function favorites(int $userId): array
    {
        return Favorite::query()->where('user_id', $userId)->latest('id')
            ->with(['subject' => fn ($q) => $q->with(['interpretations' => fn ($i) => $i->where('depth_level', 1), 'media'])])
            ->get()
            ->filter(fn (Favorite $f) => $f->subject && $f->subject->status === 'published')
            ->map(fn (Favorite $f) => [
                'id' => $f->subject->id,
                'type' => $f->subject_type,
                'slug' => $f->subject->slug,
                'title' => $f->subject->title,
                'cover' => $f->subject->coverImage()?->url,
                'url' => localized_route(self::FAVORITE_ROUTES[$f->subject_type], [$f->subject_type => $f->subject->slug]),
            ])->values()->all();
    }
}
