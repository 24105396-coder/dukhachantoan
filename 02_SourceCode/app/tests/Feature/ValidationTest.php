<?php

namespace Tests\Feature;

use App\Models\TourRoute;
use App\Models\TourSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ValidationTest extends TestCase
{
    use RefreshDatabase;

    private function bookingPayload(array $override = []): array
    {
        return array_merge([
            'quantity' => 2, 'contact_name' => 'Trần Thị B', 'contact_phone' => '0901234567', 'contact_email' => 'b@example.com',
        ], $override);
    }

    public function test_booking_quantity_must_be_between_1_and_10(): void
    {
        $slot = TourSlot::factory()->create();
        $user = $this->member();

        $this->actingAs($user)->postJson("/vi/dat-lich/{$slot->id}", $this->bookingPayload(['quantity' => 0]))
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->actingAs($user)->postJson("/vi/dat-lich/{$slot->id}", $this->bookingPayload(['quantity' => 11]))
            ->assertUnprocessable()->assertJsonValidationErrors(['quantity' => 'Mỗi lần đặt tối đa 10 khách.']);
        $this->assertSame(0, $slot->fresh()->booked_count);
    }

    public function test_booking_rejects_invalid_phone_and_email(): void
    {
        $slot = TourSlot::factory()->create();

        $this->actingAs($this->member())->postJson("/vi/dat-lich/{$slot->id}", $this->bookingPayload([
            'contact_phone' => 'abc123', 'contact_email' => 'khong-phai-email',
        ]))->assertUnprocessable()->assertJsonValidationErrors(['contact_phone', 'contact_email']);
    }

    public function test_register_requires_valid_unique_email_and_confirmed_password(): void
    {
        $existing = $this->member();

        $this->postJson('/vi/dang-ky', [
            'name' => 'A', 'email' => $existing->email, 'password' => 'short', 'password_confirmation' => 'other',
        ])->assertUnprocessable()->assertJsonValidationErrors(['name', 'email', 'password']);
    }

    public function test_register_password_requires_mixed_case_and_digit(): void
    {
        $payload = ['name' => 'Kiểm Thử V5', 'email' => 'qa_mixed@disanso.test'];

        // Chỉ chữ thường + số (ca TC-AUTH-008) → từ chối, thông báo tiếng Việt
        $response = $this->postJson('/vi/dang-ky', $payload + ['password' => 'kiemthu2026', 'password_confirmation' => 'kiemthu2026']);
        $response->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->assertContains('Trường mật khẩu phải chứa ít nhất một chữ hoa và một chữ thường.', $response->json('errors.password'));

        // Không có chữ số → từ chối, thông báo tiếng Anh dưới /en
        $response = $this->postJson('/en/dang-ky', $payload + ['password' => 'KiemThuAbc', 'password_confirmation' => 'KiemThuAbc']);
        $response->assertUnprocessable();
        $this->assertContains('The password field must contain at least one number.', $response->json('errors.password'));

        $this->assertDatabaseMissing('users', ['email' => 'qa_mixed@disanso.test']);

        // Đủ chữ hoa, chữ thường, chữ số → tạo được
        $this->post('/vi/dang-ky', $payload + ['password' => 'KiemThu2026', 'password_confirmation' => 'KiemThu2026'])
            ->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'qa_mixed@disanso.test']);
    }

    public function test_password_change_requires_mixed_case(): void
    {
        $user = $this->member();

        $this->actingAs($user)->patchJson('/vi/tai-khoan/mat-khau', [
            'current_password' => 'password', 'password' => 'matkhaumoi123', 'password_confirmation' => 'matkhaumoi123',
        ])->assertUnprocessable()->assertJsonValidationErrors('password');

        $this->actingAs($user)->patch('/vi/tai-khoan/mat-khau', [
            'current_password' => 'password', 'password' => 'MatKhauMoi123', 'password_confirmation' => 'MatKhauMoi123',
        ])->assertSessionHasNoErrors();
    }

    public function test_validation_messages_are_vietnamese_with_attribute_names(): void
    {
        $response = $this->postJson('/vi/dang-ky', []);

        $response->assertUnprocessable();
        $this->assertSame('Vui lòng nhập email.', $response->json('errors.email.0'));
        $this->assertSame('Vui lòng nhập họ tên.', $response->json('errors.name.0'));
    }

    public function test_validation_messages_are_english_under_en_prefix(): void
    {
        $response = $this->postJson('/en/dang-ky', []);

        $this->assertSame('The email field is required.', $response->json('errors.email.0'));
    }

    public function test_contribution_body_too_short_and_bad_attachment_rejected(): void
    {
        Storage::fake('public');

        $this->actingAs($this->member())->postJson('/vi/dong-gop', [
            'contribution_type' => 'story', 'locale' => 'vi', 'title' => 'Ngắn', 'body' => 'quá ngắn',
            'attachment' => UploadedFile::fake()->create('virus.exe', 10),
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'body', 'attachment']);
    }

    public function test_contribution_type_must_be_in_enum(): void
    {
        $this->actingAs($this->member())->postJson('/vi/dong-gop', [
            'contribution_type' => 'hack', 'locale' => 'fr', 'title' => 'Tiêu đề hợp lệ', 'body' => str_repeat('Nội dung dài. ', 5),
        ])->assertUnprocessable()->assertJsonValidationErrors(['contribution_type', 'locale']);
    }

    public function test_profile_rejects_unsupported_locale_and_theme(): void
    {
        $this->actingAs($this->member())->patchJson('/vi/tai-khoan/ho-so', [
            'name' => 'Người dùng', 'locale' => 'fr', 'theme' => 'neon',
        ])->assertUnprocessable()->assertJsonValidationErrors(['locale', 'theme']);
    }

    public function test_password_change_requires_current_password(): void
    {
        $this->actingAs($this->member())->patchJson('/vi/tai-khoan/mat-khau', [
            'current_password' => 'sai', 'password' => 'MatKhauMoi123', 'password_confirmation' => 'MatKhauMoi123',
        ])->assertUnprocessable()->assertJsonValidationErrors(['current_password' => 'Mật khẩu không đúng.']);
    }

    public function test_admin_slot_must_start_in_future_and_capacity_positive(): void
    {
        $route = TourRoute::factory()->create();

        $this->actingAs($this->admin())->postJson("/vi/quan-tri/tuyen-tham-quan/{$route->slug}/khung-gio", [
            'starts_at' => now()->subDay()->toDateTimeString(), 'capacity' => 0, 'language' => 'de',
        ])->assertUnprocessable()->assertJsonValidationErrors(['starts_at', 'capacity', 'language']);
    }

    public function test_admin_site_requires_coordinates_inside_vietnam_and_valid_slug(): void
    {
        $this->actingAs($this->admin())->postJson('/vi/quan-tri/di-san', [
            'slug' => 'Không Hợp Lệ!', 'site_type' => 'castle', 'province_id' => 999, 'lat' => 48.85, 'lng' => 2.35,
            'recognition_level' => 'unesco', 'status' => 'published', 'interpretations' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['slug', 'site_type', 'province_id', 'lat', 'lng', 'interpretations']);
    }

    public function test_media_upload_rejects_disallowed_extension_and_oversize(): void
    {
        Storage::fake('public');
        $route = TourRoute::factory()->create();

        $this->actingAs($this->admin())->postJson('/vi/quan-tri/media', [
            'subject_type' => 'route', 'subject_id' => $route->id, 'media_type' => 'image',
            'file' => UploadedFile::fake()->create('shell.php', 10, 'application/x-php'),
            'caption_vi' => 'Ảnh', 'source_name' => 'Nhóm', 'license' => 'cc_by',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');

        $this->actingAs($this->admin())->postJson('/vi/quan-tri/media', [
            'subject_type' => 'route', 'subject_id' => $route->id, 'media_type' => 'audio',
            'file' => UploadedFile::fake()->create('big.mp3', 21000, 'audio/mpeg'),
            'caption_vi' => 'Âm thanh', 'source_name' => 'Nhóm', 'license' => 'cc_by',
        ])->assertUnprocessable()->assertJsonValidationErrors('file');
    }
}
