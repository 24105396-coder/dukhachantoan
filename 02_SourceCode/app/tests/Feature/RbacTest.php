<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Contribution;
use App\Models\TourRoute;
use App\Models\TourSlot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RbacTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_admin(): void
    {
        $this->get('/vi/quan-tri')->assertRedirect('/vi/dang-nhap');
        $this->assertSame(url('/vi/quan-tri'), session('url.intended'));

        $this->get('/en/quan-tri/di-san')->assertRedirect('/en/dang-nhap');
        $this->post('/vi/quan-tri/di-san', [])->assertRedirect('/vi/dang-nhap');
    }

    public function test_guest_returns_to_admin_after_login(): void
    {
        $admin = $this->admin();
        $this->get('/vi/quan-tri')->assertRedirect('/vi/dang-nhap');

        $this->post('/vi/dang-nhap', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(url('/vi/quan-tri'));
    }

    public function test_member_cannot_access_admin_dashboard(): void
    {
        $this->actingAs($this->member())->get('/vi/quan-tri')->assertForbidden();
    }

    public function test_contributor_cannot_access_admin_dashboard_or_sites(): void
    {
        $contributor = $this->contributor();
        $this->actingAs($contributor)->get('/vi/quan-tri')->assertForbidden();
        $this->actingAs($contributor)->get('/vi/quan-tri/di-san')->assertForbidden();
    }

    public function test_contributor_cannot_edit_route_of_another_owner(): void
    {
        $route = TourRoute::factory()->ownedBy($this->contributor())->create();
        $other = $this->contributor();

        $this->actingAs($other)->get("/vi/quan-tri/tuyen-tham-quan/{$route->slug}/sua")->assertForbidden();
        $this->actingAs($other)->post("/vi/quan-tri/tuyen-tham-quan/{$route->slug}/khung-gio", [
            'starts_at' => now()->addDays(5)->toDateTimeString(), 'capacity' => 10, 'language' => 'vi',
        ])->assertForbidden();
        $this->actingAs($other)->delete("/vi/quan-tri/tuyen-tham-quan/{$route->slug}")->assertForbidden();
        $this->assertNotSoftDeleted($route);
    }

    public function test_contributor_can_edit_own_route(): void
    {
        $owner = $this->contributor();
        $route = TourRoute::factory()->ownedBy($owner)->create();

        $this->actingAs($owner)->get("/vi/quan-tri/tuyen-tham-quan/{$route->slug}/sua")->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Tours/Form')->where('route.slug', $route->slug));
    }

    public function test_contributor_tour_index_lists_only_own_routes(): void
    {
        $owner = $this->contributor();
        TourRoute::factory()->ownedBy($owner)->create();
        TourRoute::factory()->create();

        $this->actingAs($owner)->get('/vi/quan-tri/tuyen-tham-quan')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Tours/Index')->has('routes.data', 1));
    }

    public function test_contributor_sees_only_bookings_of_own_routes(): void
    {
        $owner = $this->contributor();
        $ownSlot = TourSlot::factory()->for(TourRoute::factory()->ownedBy($owner), 'route')->create();
        Booking::factory()->for($ownSlot, 'slot')->create();
        Booking::factory()->create();

        $this->actingAs($owner)->get('/vi/quan-tri/dat-lich')->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Admin/Bookings/Index')->has('bookings.data', 1));
    }

    public function test_member_cannot_change_booking_status_via_admin_route(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs($this->member())->patch("/vi/quan-tri/dat-lich/{$booking->code}/trang-thai", ['status' => 'cancelled'])
            ->assertForbidden();
        $this->assertSame('held', $booking->fresh()->status);
    }

    public function test_manager_cannot_manage_users_or_view_audit(): void
    {
        $manager = $this->manager();
        $target = $this->member();

        $this->actingAs($manager)->get('/vi/quan-tri/nguoi-dung')->assertForbidden();
        $this->actingAs($manager)->get('/vi/quan-tri/nhat-ky')->assertForbidden();
        $this->actingAs($manager)->patch("/vi/quan-tri/nguoi-dung/{$target->id}", ['role_id' => $manager->role_id, 'is_active' => true])
            ->assertForbidden();
    }

    public function test_member_cannot_view_booking_of_another_user(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAs($this->member())->get("/vi/dat-lich/{$booking->code}")->assertForbidden();
        $this->actingAs($this->member())->post("/vi/dat-lich/{$booking->code}/thanh-toan")->assertForbidden();
    }

    public function test_member_cannot_view_contribution_of_another_user(): void
    {
        $contribution = Contribution::create([
            'user_id' => $this->member()->id, 'contribution_type' => 'story', 'locale' => 'vi',
            'title' => 'Câu chuyện làng', 'body' => str_repeat('Nội dung ', 5), 'status' => 'pending',
        ]);

        $this->actingAs($this->member())->get("/vi/dong-gop/{$contribution->id}")->assertForbidden();
    }

    public function test_admin_can_access_everything(): void
    {
        $admin = $this->admin();
        foreach (['/vi/quan-tri', '/vi/quan-tri/di-san', '/vi/quan-tri/nguoi-dung', '/vi/quan-tri/nhat-ky', '/vi/quan-tri/kiem-duyet', '/vi/quan-tri/media', '/vi/quan-tri/chat-luong-du-lieu', '/vi/quan-tri/the', '/vi/quan-tri/hien-vat', '/vi/quan-tri/nhan-vat'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_admin_cannot_demote_self(): void
    {
        $admin = $this->admin();
        $memberRoleId = $this->member()->role_id;

        $this->actingAs($admin)->patch("/vi/quan-tri/nguoi-dung/{$admin->id}", ['role_id' => $memberRoleId, 'is_active' => true])
            ->assertSessionHasErrors('role_id');
        $this->assertSame('admin', $admin->fresh()->role->code);
    }

    public function test_inactive_staff_is_blocked(): void
    {
        $user = User::factory()->admin()->inactive()->create();

        // M1: tài khoản bị khóa bị đăng xuất ngay ở mọi route web, quay về trang đăng nhập
        $this->actingAs($user)->get('/vi/quan-tri')->assertRedirect(localized_route('login', ['locale' => 'vi']));
        $this->assertGuest();
    }
}
