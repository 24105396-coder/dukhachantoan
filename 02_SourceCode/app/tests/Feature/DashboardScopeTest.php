<?php

namespace Tests\Feature;

use App\Models\Notification;
use App\Models\TripLeg;
use App\Models\User;
use App\Models\Zone;
use Database\Factories\FactoryHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * queueHealth và thống kê thông báo của agency chỉ tính thông báo thuộc tỉnh mình
 * (hành trình có chặng trong tỉnh hoặc người nhận thuộc tỉnh); admin thấy toàn hệ thống.
 */
class DashboardScopeTest extends TestCase
{
    use RefreshDatabase;

    /** 1 queued + 1 failed thuộc Khánh Hòa, 1 queued + 1 failed thuộc Hà Nội. */
    private function seedNotifications(): void
    {
        $kh = FactoryHelpers::provinceId('KH');
        $hn = FactoryHelpers::provinceId('HN');
        $khLeg = TripLeg::factory()->create(['zone_id' => Zone::factory()->create(['province_id' => $kh])->id]);
        $hnLeg = TripLeg::factory()->create(['zone_id' => Zone::factory()->create(['province_id' => $hn])->id]);

        Notification::factory()->create(['trip_id' => $khLeg->trip_id, 'user_id' => $khLeg->trip->user_id]);
        Notification::factory()->failed()->create(['user_id' => User::factory()->traveler()->create(['province_id' => $kh])->id]);
        Notification::factory()->create(['trip_id' => $hnLeg->trip_id, 'user_id' => $hnLeg->trip->user_id]);
        Notification::factory()->failed()->create(['user_id' => User::factory()->traveler()->create(['province_id' => $hn])->id]);
    }

    public function test_agency_dashboard_queue_health_and_notification_kpis_are_province_scoped(): void
    {
        $this->seedNotifications();
        $agency = User::factory()->agency('KH')->create();

        $this->actingAs($agency)->get('/vi/quan-tri')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Dashboard')
                ->where('queueHealth.queued', 1)
                ->where('queueHealth.failed', 1)
                ->where('kpis.notifications_24h.queued', 1)
                ->where('kpis.notifications_24h.failed', 1));
    }

    public function test_admin_dashboard_queue_health_covers_whole_system(): void
    {
        $this->seedNotifications();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get('/vi/quan-tri')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('queueHealth.queued', 2)
                ->where('queueHealth.failed', 2)
                ->where('kpis.notifications_24h.queued', 2)
                ->where('kpis.notifications_24h.failed', 2));
    }

    public function test_agency_notification_queue_page_stats_are_province_scoped(): void
    {
        $this->seedNotifications();
        $agency = User::factory()->agency('KH')->create();

        $this->actingAs($agency)->get('/vi/quan-tri/thong-bao')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Notifications/Index')
                ->where('stats.queued', 1)
                ->where('stats.failed', 1)
                ->where('stats.queue.queued', 1)
                ->where('stats.queue.failed', 1)
                ->has('notifications.data', 2));
    }
}
