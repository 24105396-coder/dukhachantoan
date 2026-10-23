<?php

namespace Tests\Feature;

use App\Models\SafetyCheckin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** BUG-7: thông điệp lỗi máy chủ dùng tên trường tự nhiên, không lộ khóa kỹ thuật (legs.0.zone_code, response note). */
class ValidationMessagesTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    private function tripPayload(string $zoneCode): array
    {
        $day = now()->addDay()->startOfDay();

        return [
            'title' => 'Nha Trang', 'start_date' => $day->toDateString(), 'end_date' => $day->copy()->addDay()->toDateString(),
            'group_size' => 2, 'preferred_locale' => 'vi',
            'legs' => [['zone_code' => $zoneCode, 'starts_at' => $day->copy()->setTime(9, 0)->toDateTimeString(),
                'ends_at' => $day->copy()->setTime(18, 0)->toDateTimeString()]],
            'members' => [['full_name' => '', 'phone' => 'abc']],
        ];
    }

    /** Thông điệp đầu tiên của một khóa lỗi (khóa có dấu chấm nên không dùng assertJsonPath). */
    private function firstError(\Illuminate\Testing\TestResponse $response, string $key): ?string
    {
        return $response->assertStatus(422)->json('errors')[$key][0] ?? null;
    }

    public function test_nested_trip_errors_use_natural_vietnamese_labels(): void
    {
        $response = $this->actingAs($this->traveler())->postJson('/vi/hanh-trinh/tao', $this->tripPayload('khong-co-vung'));

        $this->assertSame('Vùng của chặng 1 không tồn tại.', $this->firstError($response, 'legs.0.zone_code'));
        $this->assertSame('Vui lòng nhập họ tên thành viên 1.', $this->firstError($response, 'members.0.full_name'));
        $this->assertStringContainsString('số điện thoại thành viên 1', $this->firstError($response, 'members.0.phone'));
        $messages = implode(' ', array_merge(...array_values($response->json('errors'))));
        $this->assertStringNotContainsString('legs.0', $messages);
        $this->assertStringNotContainsString('members.0', $messages);
    }

    public function test_nested_trip_errors_in_english(): void
    {
        $response = $this->actingAs($this->traveler())->postJson('/en/hanh-trinh/tao', $this->tripPayload('no-such-zone'));

        $this->assertSame('The zone of leg 1 does not exist.', $this->firstError($response, 'legs.0.zone_code'));
    }

    public function test_closing_checkin_without_note_shows_natural_message(): void
    {
        $trip = $this->tripInZone($this->zoneIn('KH'));
        $checkin = SafetyCheckin::create(['trip_id' => $trip->id, 'user_id' => $trip->user_id, 'status' => 'need_help',
            'response_status' => 'open', 'checked_at' => now()]);

        $this->actingAs($this->agency('KH'))->postJson("/vi/quan-tri/an-toan/{$checkin->id}/phan-hoi", ['response_status' => 'closed'])
            ->assertStatus(422)
            ->assertJsonPath('errors.response_note.0', 'Vui lòng nhập ghi chú phản hồi khi đóng yêu cầu.');
    }

    public function test_alert_form_nested_labels(): void
    {
        $response = $this->actingAs($this->agency('KH'))->postJson('/vi/quan-tri/canh-bao/tao', [
            'severity' => 2, 'source_name' => 'Nguồn', 'zone_codes' => ['khong-co'], 'translations' => ['vi' => ['headline' => '']],
        ]);

        $this->assertSame('Vui lòng nhập tiêu đề cảnh báo (tiếng Việt).', $this->firstError($response, 'translations.vi.headline'));
        $this->assertSame('Giá trị vùng ảnh hưởng thứ 1 đã chọn không tồn tại.', $this->firstError($response, 'zone_codes.0'));
    }
}
