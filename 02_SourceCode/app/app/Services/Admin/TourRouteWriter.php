<?php

namespace App\Services\Admin;

use App\Models\TourRoute;
use App\Models\TourSlot;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Lưu tuyến tham quan (bản dịch + điểm dừng) và khung giờ. */
class TourRouteWriter
{
    public const FIELDS = ['slug', 'province_id', 'theme_tag_id', 'duration_minutes', 'distance_km', 'difficulty', 'price_vnd', 'default_capacity', 'status'];

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function save(array $data, User $user, ?TourRoute $route = null): TourRoute
    {
        $route ??= new TourRoute();
        $isNew = ! $route->exists;

        DB::transaction(function () use ($route, $data, $user, $isNew) {
            $route->fill(array_intersect_key($data, array_flip(self::FIELDS)));
            if ($isNew) {
                $route->created_by = $user->id;
            }
            $route->save();

            foreach (['vi', 'en'] as $locale) {
                $t = $data['translations'][$locale] ?? null;
                if (! empty($t['name'])) {
                    $route->translations()->updateOrCreate(['locale' => $locale], [
                        'name' => $t['name'], 'description' => $t['description'] ?? null, 'highlights' => $t['highlights'] ?? null,
                    ]);
                }
            }

            $route->stops()->delete();
            foreach (array_values($data['stops']) as $i => $stop) {
                $route->stops()->create([
                    'stop_order' => $i + 1,
                    'site_id' => $stop['site_id'],
                    'dwell_minutes' => $stop['dwell_minutes'] ?? 30,
                    'note' => $stop['note'] ?? null,
                ]);
            }
        });

        $this->audit->log('admin.route.'.($isNew ? 'create' : 'update'), $route, ['slug' => $route->slug], $user);

        return $route;
    }

    public function addSlot(TourRoute $route, array $data, User $user): TourSlot
    {
        $start = Carbon::parse($data['starts_at']);
        $slot = $route->slots()->create([
            'starts_at' => $start,
            'ends_at' => $start->copy()->addMinutes((int) ($data['duration_minutes'] ?? $route->duration_minutes)),
            'capacity' => $data['capacity'],
            'booked_count' => 0,
            'guide_name' => $data['guide_name'] ?? null,
            'language' => $data['language'],
            'status' => 'open',
        ]);
        $this->audit->log('admin.slot.create', $slot, ['route' => $route->slug, 'starts_at' => $slot->starts_at->toIso8601String()], $user);

        return $slot;
    }

    /** Chỉ xóa được khi chưa có đặt lịch nào. */
    public function deleteSlot(TourSlot $slot, User $user): bool
    {
        if ($slot->bookings()->exists()) {
            return false;
        }
        $this->audit->log('admin.slot.delete', $slot, ['starts_at' => $slot->starts_at?->toIso8601String()], $user);
        $slot->delete();

        return true;
    }
}
