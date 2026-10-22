<?php

namespace Database\Seeders;

use App\Models\HeritageSite;
use App\Models\Province;
use App\Models\Tag;
use App\Models\TourRoute;
use App\Models\TourRouteStop;
use App\Models\TourRouteTranslation;
use App\Models\TourSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\Concerns\ReadsSeedJson;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class RouteSeeder extends Seeder
{
    use ReadsSeedJson;

    /** Mỗi tuyến 10 khung giờ: 5 ngày × (09:00, 14:00) rải trong 30 ngày tới. */
    private const SLOT_DAYS = [2, 7, 12, 18, 25];
    private const SLOT_HOURS = [9, 14];

    public function run(): void
    {
        $provinces = Province::pluck('id', 'code');
        $sites = HeritageSite::pluck('id', 'slug');
        $partnerId = User::where('email', 'partner@disanso.test')->value('id');

        foreach (array_values($this->readJson('routes.json')) as $index => $row) {
            if (empty($row['slug'])) {
                continue;
            }
            $route = TourRoute::withTrashed()->updateOrCreate(['slug' => $row['slug']], [
                'province_id' => $provinces[$row['province'] ?? ''] ?? null,
                'theme_tag_id' => isset($row['theme_tag']) ? Tag::where('slug', $row['theme_tag'])->value('id') : null,
                'duration_minutes' => (int) ($row['duration_minutes'] ?? 240),
                'distance_km' => $row['distance_km'] ?? null,
                'difficulty' => in_array($row['difficulty'] ?? null, ['de', 'vua', 'kho'], true) ? $row['difficulty'] : 'de',
                'price_vnd' => (int) ($row['price_vnd'] ?? 0),
                'default_capacity' => (int) ($row['default_capacity'] ?? 20),
                'status' => $row['status'] ?? 'published',
                // Tuyến chỉ số lẻ thuộc tài khoản đối tác để minh họa phân quyền contributor.
                'created_by' => $index % 2 === 1 ? $partnerId : null,
            ]);
            $this->syncTranslations($route, $row);
            $this->syncStops($route, $row['stops'] ?? [], $sites->all());
            $this->ensureSlots($route, $index);
        }
    }

    private function syncTranslations(TourRoute $route, array $row): void
    {
        foreach (['vi', 'en'] as $locale) {
            if (! empty($row[$locale]['name'])) {
                TourRouteTranslation::updateOrCreate(['route_id' => $route->id, 'locale' => $locale], [
                    'name' => mb_substr($row[$locale]['name'], 0, 200),
                    'description' => $row[$locale]['description'] ?? null,
                    'highlights' => $row[$locale]['highlights'] ?? null,
                ]);
            }
        }
    }

    private function syncStops(TourRoute $route, array $stops, array $siteIds): void
    {
        TourRouteStop::where('route_id', $route->id)->delete();
        $order = 1;
        foreach ($stops as $stop) {
            $siteId = $siteIds[$stop['site'] ?? ''] ?? null;
            if (! $siteId) {
                continue;
            }
            TourRouteStop::create([
                'route_id' => $route->id,
                'stop_order' => $order++,
                'site_id' => $siteId,
                'dwell_minutes' => (int) ($stop['dwell_minutes'] ?? 30),
                'note' => isset($stop['note']) ? mb_substr($stop['note'], 0, 255) : null,
            ]);
        }
    }

    private function ensureSlots(TourRoute $route, int $index): void
    {
        $faker = Faker::create('vi_VN');
        $faker->seed(100 + $index);
        $today = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfDay();
        foreach (self::SLOT_DAYS as $d => $day) {
            foreach (self::SLOT_HOURS as $h => $hour) {
                $start = $today->addDays($day + ($index % 3))->setTime($hour, 0)->setTimezone(config('app.timezone'));
                $slot = TourSlot::firstOrNew(['route_id' => $route->id, 'starts_at' => $start]);
                $slot->fill([
                    'ends_at' => $start->addMinutes($route->duration_minutes),
                    'capacity' => max($route->default_capacity, $slot->booked_count ?? 0),
                    'guide_name' => $slot->guide_name ?? $faker->name(),
                    'language' => ($d + $h) % 4 === 3 ? 'en' : 'vi',
                    'status' => $slot->status ?? 'open',
                ]);
                $slot->booked_count ??= 0;
                $slot->save();
            }
        }
    }
}
