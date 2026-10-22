<?php

namespace Tests\Feature;

use App\Http\Resources\BookingItemResource;
use App\Models\Booking;
use App\Support\UrlToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Mục 6.6 TLKT: tra cứu đặt lịch bằng id mã hóa Crypt; token hỏng → 404, sai chủ → 403. */
class BookingLookupTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_round_trip_and_purpose_binding(): void
    {
        $token = UrlToken::encode('booking', 42);

        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);
        $this->assertStringNotContainsString('42', base64_decode(strtr($token, '-_', '+/')) ?: '');
        $this->assertSame(42, UrlToken::decode('booking', $token));
        $this->assertNull(UrlToken::decode('contribution-attachment', $token));
        $this->assertNull(UrlToken::decode('booking', $token.'x'));
        $this->assertNull(UrlToken::decode('booking', 'abc'));
    }

    public function test_owner_opens_booking_by_encrypted_token(): void
    {
        $owner = $this->member();
        $booking = Booking::factory()->for($owner)->create();
        $url = '/vi/dat-lich/tra-cuu/'.UrlToken::encode(BookingItemResource::URL_TOKEN_PURPOSE, $booking->id);

        $this->actingAs($owner)->get($url)->assertOk()
            ->assertInertia(fn (Assert $p) => $p->component('Bookings/Show')
                ->where('booking.code', $booking->code)
                ->where('booking.lookup_url', fn ($u) => str_contains($u, '/vi/dat-lich/tra-cuu/') && ! str_contains($u, '/'.$booking->id)));
    }

    public function test_invalid_token_returns_404(): void
    {
        $owner = $this->member();
        Booking::factory()->for($owner)->create();

        $this->actingAs($owner)->get('/vi/dat-lich/tra-cuu/khong-hop-le')->assertNotFound();
        $this->actingAs($owner)->get('/vi/dat-lich/tra-cuu/'.UrlToken::encode('contribution-attachment', 1))->assertNotFound();
        $this->actingAs($owner)->get('/vi/dat-lich/tra-cuu/'.UrlToken::encode('booking', 999999))->assertNotFound();
    }

    public function test_other_member_token_is_forbidden_and_guest_redirected(): void
    {
        $booking = Booking::factory()->for($this->member())->create();
        $url = '/vi/dat-lich/tra-cuu/'.UrlToken::encode('booking', $booking->id);

        $this->actingAs($this->member())->get($url)->assertForbidden();
        auth()->logout();
        $this->get($url)->assertRedirect('/vi/dang-nhap');
    }
}
