<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\AuditLog;
use App\Models\Notification;
use App\Models\Province;
use App\Services\Reports\ExportDatasets;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Tests\Support\RiskFixtures;
use Tests\TestCase;

/** Mục 1.1 góp ý 24/10: trang Báo cáo + xuất Excel (RBAC, phạm vi tỉnh, tệp đọc lại được, nhật ký, throttle). */
class ReportExportTest extends TestCase
{
    use RefreshDatabase, RiskFixtures;

    /** 2 cảnh báo Khánh Hòa (1 thật mức 4, 1 mô phỏng mức 2) + 1 cảnh báo Hà Nội; thông báo 2 gửi được, 1 thất bại. */
    private function seedData(): array
    {
        $kh = $this->zoneIn('KH');
        $hn = $this->zoneIn('HN');
        $real = $this->alertForZones([$kh], 4, Alert::STATUS_ACTIVE, ['is_simulated' => false, 'source_name' => 'Open-Meteo']);
        $this->alertForZones([$kh], 2);
        $this->alertForZones([$hn], 3);
        $trip = $this->tripInZone($kh);
        $this->tripInZone($hn);
        Notification::factory()->sent()->count(2)->create(['trip_id' => $trip->id]);
        Notification::factory()->failed()->create(['trip_id' => $trip->id]);

        return compact('kh', 'hn', 'real', 'trip');
    }

    private function workbook(TestResponse $response): Spreadsheet
    {
        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        try {
            return IOFactory::createReader('Xlsx')->load($path);
        } finally {
            @unlink($path);
        }
    }

    public function test_guest_traveler_and_partner_cannot_open_reports_or_exports(): void
    {
        $this->get('/vi/quan-tri/bao-cao')->assertRedirect('/vi/dang-nhap');
        $this->get('/vi/quan-tri/xuat-excel/canh-bao')->assertRedirect('/vi/dang-nhap');
        foreach ([$this->traveler(), $this->partner()] as $user) {
            $this->actingAs($user)->get('/vi/quan-tri/bao-cao')->assertForbidden();
            $this->actingAs($user)->get('/vi/quan-tri/xuat-excel/canh-bao')->assertForbidden();
        }
        $this->assertSame(0, AuditLog::where('action', 'like', 'export.%')->count());
    }

    public function test_admin_report_page_has_contract_props_and_counts(): void
    {
        $this->seedData();

        $this->actingAs($this->admin())->get('/vi/quan-tri/bao-cao')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Reports/Index', false)
            ->where('filters.from', now()->subDays(90)->toDateString())
            ->where('filters.to', now()->toDateString())
            ->where('filters.province', null)
            ->where('summary.alerts_total', 3)
            ->where('summary.alerts_live', 3)
            ->where('summary.real_alerts', 1)
            ->where('summary.simulated_alerts', 2)
            ->where('summary.trips_total', 2)
            ->where('summary.notifications_sent', 2)
            ->where('summary.notifications_failed', 1)
            ->where('summary.delivery_rate_pct', 66.7)
            ->has('summary', 12)
            ->has('byLevel', 4)
            ->where('byLevel.3.count', 1)
            ->has('byHazard', 1)
            ->has('byProvince', 2)
            ->where('byProvince.0.name', 'Khánh Hòa')
            ->where('byProvince.0.alerts', 2)
            ->has('byMonth')
            ->has('bySource', 1, fn (Assert $s) => $s->where('kind', 'manual')->where('alerts', 3)->etc())
            ->has('datasets', 8)
            ->where('datasets.1.key', 'canh-bao')
            ->where('datasets.1.count', 3)
            ->where('datasets.1.url', fn ($url) => str_contains($url, '/vi/quan-tri/xuat-excel/canh-bao?from='))
            ->has('provinces', 2)
            ->where('scope.province', null)
            ->has('generated_at'));
    }

    public function test_agency_is_locked_to_own_province_and_has_no_audit_dataset(): void
    {
        $this->seedData();
        $hnId = Province::where('code', 'HN')->value('id');

        $this->actingAs($this->agency('KH'))->get("/vi/quan-tri/bao-cao?province={$hnId}")->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('scope.province.code', 'KH')
                ->where('summary.alerts_total', 2)
                ->has('byProvince', 1)
                ->where('provinces', [])
                ->has('datasets', 7)
                ->where('datasets', fn ($sets) => ! collect($sets)->contains('key', 'nhat-ky')));
        $this->actingAs($this->agency('KH'))->get('/vi/quan-tri/xuat-excel/nhat-ky')->assertForbidden();
    }

    public function test_alert_export_is_real_xlsx_with_meta_sheet_and_audit_log(): void
    {
        $this->seedData();
        $admin = $this->admin();

        $response = $this->actingAs($admin)->get('/vi/quan-tri/xuat-excel/canh-bao')->assertOk();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertMatchesRegularExpression('/DuKhachAnToan_canh-bao_\d{8}_\d{4}\.xlsx/', $response->headers->get('Content-Disposition'));
        $book = $this->workbook($response);

        $this->assertSame(['Meta', 'Cảnh báo'], $book->getSheetNames());
        $meta = $book->getSheetByName('Meta')->toArray();
        $this->assertContains(['Số dòng', '3'], $meta);
        $this->assertContains(['Phạm vi tỉnh', 'Toàn quốc'], $meta);
        $sheet = $book->getSheetByName('Cảnh báo');
        $headers = $sheet->rangeToArray('A1:N1')[0];
        foreach (['Nguồn', 'Cập nhật nguồn', 'Mô phỏng'] as $required) {
            $this->assertContains($required, $headers);
        }
        $this->assertSame(4, $sheet->getHighestDataRow(), 'tiêu đề + 3 cảnh báo');
        $this->assertTrue($sheet->getStyle('A1')->getFont()->getBold());
        $this->assertSame('A2', $sheet->getFreezePane());
        $this->assertSame('dd/mm/yyyy hh:mm', $sheet->getStyle('H2')->getNumberFormat()->getFormatCode());
        $simulated = array_column($sheet->rangeToArray('N2:N4'), 0);
        $this->assertEqualsCanonicalizing(['Có', 'Có', 'Không'], $simulated);

        $log = AuditLog::where('action', 'export.canh-bao')->sole();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame(3, $log->meta['rows']);
        $this->assertFalse($log->meta['truncated']);
    }

    public function test_agency_export_contains_only_own_province_rows(): void
    {
        $this->seedData();

        $book = $this->workbook($this->actingAs($this->agency('KH'))->get('/vi/quan-tri/xuat-excel/canh-bao')->assertOk());

        $this->assertSame(3, $book->getSheetByName('Cảnh báo')->getHighestDataRow(), 'tiêu đề + 2 cảnh báo Khánh Hòa');
        $this->assertContains(['Phạm vi tỉnh', 'Khánh Hòa'], $book->getSheetByName('Meta')->toArray());
    }

    public function test_summary_export_has_meta_and_six_report_sheets(): void
    {
        $this->seedData();

        $book = $this->workbook($this->actingAs($this->admin())->get('/vi/quan-tri/xuat-excel/tong-hop')->assertOk());

        $this->assertSame(['Meta', 'Tổng quan', 'Theo mức', 'Theo loại', 'Theo tỉnh', 'Theo tháng', 'Theo nguồn'], $book->getSheetNames());
        $this->assertSame(13, $book->getSheetByName('Tổng quan')->getHighestDataRow(), 'tiêu đề + 12 chỉ số');
        $this->assertSame(5, $book->getSheetByName('Theo mức')->getHighestDataRow());
        $this->assertDatabaseHas('audit_logs', ['action' => 'export.tong-hop']);
    }

    public function test_every_dataset_downloads_for_admin_and_headers_follow_locale(): void
    {
        $this->seedData();
        $admin = $this->admin();

        foreach (ExportDatasets::KEYS as $key) {
            $book = $this->workbook($this->actingAs($admin)->get("/vi/quan-tri/xuat-excel/{$key}")->assertOk());
            $this->assertSame('Meta', $book->getSheetNames()[0], $key);
        }
        $book = $this->workbook($this->actingAs($admin)->get('/en/quan-tri/xuat-excel/danh-ba')->assertOk());
        $this->assertSame(['Meta', 'Contacts'], $book->getSheetNames());
        $this->assertContains('Source', $book->getSheetByName('Contacts')->rangeToArray('A1:N1')[0]);
        $this->actingAs($admin)->get('/vi/quan-tri/xuat-excel/khong-co')->assertNotFound();
    }

    public function test_user_text_starting_with_equals_is_written_as_text_not_formula(): void
    {
        $alert = $this->alertForZones([$this->zoneIn('KH')], 3);
        $alert->syncTranslations(['vi' => ['headline' => '=HYPERLINK("http://evil.test","x")']]);

        $sheet = $this->workbook($this->actingAs($this->admin())->get('/vi/quan-tri/xuat-excel/canh-bao')->assertOk())
            ->getSheetByName('Cảnh báo');

        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('E2')->getDataType());
        $this->assertSame('=HYPERLINK("http://evil.test","x")', $sheet->getCell('E2')->getValue());
    }

    public function test_filters_are_validated_and_exports_are_throttled(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin)->getJson('/vi/quan-tri/bao-cao?from=2026-13-01')->assertStatus(422)->assertJsonValidationErrors('from');
        $this->actingAs($admin)->getJson('/vi/quan-tri/xuat-excel/canh-bao?from=2026-10-10&to=2026-10-01')
            ->assertStatus(422)->assertJsonValidationErrors('to');
        $this->actingAs($admin)->getJson('/vi/quan-tri/xuat-excel/canh-bao?province=1%27%20OR%201=1')
            ->assertStatus(422)->assertJsonValidationErrors('province');

        for ($i = 0; $i < 8; $i++) {
            $this->actingAs($admin)->get('/vi/quan-tri/xuat-excel/danh-ba')->assertOk();
        }
        $this->actingAs($admin)->get('/vi/quan-tri/xuat-excel/danh-ba')->assertStatus(429);
    }

    public function test_date_range_filter_limits_rows(): void
    {
        $zone = $this->zoneIn('KH');
        $this->alertForZones([$zone], 3, Alert::STATUS_ACTIVE, ['issued_at' => now()->subDays(200)]);
        $this->alertForZones([$zone], 3);
        $from = now()->subDays(250)->toDateString();
        $to = now()->subDays(150)->toDateString();

        $this->actingAs($this->admin())->get("/vi/quan-tri/bao-cao?from={$from}&to={$to}")
            ->assertInertia(fn (Assert $page) => $page->where('summary.alerts_total', 1)->where('filters.from', $from));
    }

    public function test_export_audit_actions_have_labels_in_both_locales(): void
    {
        foreach (['vi', 'en'] as $locale) {
            $dict = json_decode(file_get_contents(lang_path("{$locale}.json")), true);
            foreach (ExportDatasets::KEYS as $key) {
                $this->assertNotEmpty($dict["enums.audit_action.export.{$key}"] ?? null, "{$locale}: export.{$key}");
            }
        }
    }
}
