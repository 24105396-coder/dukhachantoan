<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/** Lược đồ 33 bảng nghiệp vụ theo dka-conventions/references/data-model.md. */
class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public const BUSINESS_TABLES = [
        'roles', 'users', 'notification_preferences', 'provinces', 'zones', 'destinations', 'destination_translations',
        'trips', 'trip_legs', 'trip_members', 'alert_sources', 'hazard_types', 'hazard_type_translations', 'alerts',
        'alert_translations', 'alert_zones', 'weather_observations', 'weather_forecasts', 'risk_scores', 'alert_matches',
        'notifications', 'notification_deliveries', 'safety_checkins', 'incident_reports', 'emergency_contacts',
        'emergency_contact_translations', 'response_guides', 'response_guide_translations', 'simulation_scenarios',
        'simulation_runs', 'media_assets', 'media_captions', 'audit_logs',
    ];

    public const HERITAGE_TABLES = ['heritage_sites', 'artifacts', 'historical_figures', 'interpretations', 'tags', 'tour_routes', 'tour_slots', 'bookings', 'payments', 'contributions', 'favorites', 'page_views', 'search_logs', 'periods'];

    public function test_all_33_business_tables_exist(): void
    {
        $this->assertCount(33, self::BUSINESS_TABLES);
        foreach (self::BUSINESS_TABLES as $table) {
            $this->assertTrue(Schema::hasTable($table), "Thiếu bảng {$table}");
        }
    }

    public function test_heritage_tables_are_gone(): void
    {
        foreach (self::HERITAGE_TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), "Còn bảng di sản {$table}");
        }
    }

    public function test_alert_translations_has_fulltext_index(): void
    {
        $indexes = DB::select("SHOW INDEX FROM alert_translations WHERE Index_type = 'FULLTEXT'");
        $columns = collect($indexes)->pluck('Column_name')->sort()->values()->all();

        $this->assertSame(['description', 'headline'], $columns);
    }

    public function test_unique_constraints_from_data_model(): void
    {
        $unique = fn (string $table) => collect(DB::select("SHOW INDEX FROM {$table} WHERE Non_unique = 0"))
            ->groupBy('Key_name')->map(fn ($rows) => $rows->pluck('Column_name')->all())->values()->all();

        $this->assertContains(['zone_id', 'observed_at'], $unique('weather_observations'));
        $this->assertContains(['zone_id', 'forecast_date'], $unique('weather_forecasts'));
        $this->assertContains(['alert_id', 'leg_id'], $unique('alert_matches'));
        $this->assertContains(['user_id'], $unique('notification_preferences'));
        $this->assertContains(['share_token'], $unique('trips'));
    }

    public function test_notifications_has_status_next_attempt_index(): void
    {
        $index = collect(DB::select('SHOW INDEX FROM notifications'))->groupBy('Key_name')
            ->map(fn ($rows) => $rows->sortBy('Seq_in_index')->pluck('Column_name')->all());

        $this->assertContains(['status', 'next_attempt_at'], $index->values()->all());
    }

    public function test_morph_map_uses_convention_aliases(): void
    {
        foreach (['zone', 'destination', 'trip', 'leg', 'alert', 'match', 'notification', 'checkin', 'incident', 'contact', 'guide', 'scenario', 'user'] as $alias) {
            $this->assertArrayHasKey($alias, AppServiceProvider::MORPH_MAP);
            $this->assertSame(AppServiceProvider::MORPH_MAP[$alias], Relation::getMorphedModel($alias));
        }
    }
}
