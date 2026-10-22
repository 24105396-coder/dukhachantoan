<?php

namespace App\Http\Controllers;

use App\Http\Requests\FavoriteToggleRequest;
use App\Models\Favorite;
use App\Models\User;
use App\Services\SearchService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

class FavoriteController extends Controller
{
    public function toggle(FavoriteToggleRequest $request): RedirectResponse
    {
        $type = $request->validated('type');
        $class = SearchService::TYPES[$type];
        $subject = $class::query()->published()->findOrFail($request->validated('id'));

        $attributes = ['user_id' => $request->user()->id, 'subject_type' => $type, 'subject_id' => $subject->id];
        $existing = Favorite::query()->where($attributes)->first();

        if ($existing) {
            $existing->delete();

            return back()->with('success', __('messages.favorites.removed'));
        }
        Favorite::create($attributes);

        return back()->with('success', __('messages.favorites.added'));
    }

    public static function isFavorite(?User $user, Model $subject): bool
    {
        return $user !== null && Favorite::query()->where([
            'user_id' => $user->id,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ])->exists();
    }
}
