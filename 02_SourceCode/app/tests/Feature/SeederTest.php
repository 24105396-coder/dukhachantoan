<?php

namespace Tests\Feature;

use App\Models\Alert;
use App\Models\Destination;
use App\Models\EmergencyContact;
use App\Models\HazardType;
use App\Models\ResponseGuide;
use App\Models\Role;
use App\Models\SimulationScenario;
use App\Models\Trip;
use App\Models\User;
use App\Models\Zone;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\HazardTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

/** Seeder đọc JSON theo json-schema.md, chạy lại được, và không lỗi khi thiếu tệp. */
class SeederTest extends TestCase
{
    use RefreshDatabase;

    private function useFixtures(?string $dir = null): void
    {
        config(['dukhach.seed_data_path' => $dir ?? base_path('tests/Fixtures/seed')]);
    }

    public function test_seeders_read_fixture_json_and_are_idempotent(): void
    {
        $this->useFixtures();
        $this->seed(DatabaseSeeder::class);
        $counts = $this->counts();
        $this->seed(DatabaseSeeder::class);

        $this->assertSame($counts, $this->counts());
        $this->assertSame(3, $counts['zones']);
        $this->assertSame(1, $counts['destinations'], 'điểm đến có zone_code sai phải bị bỏ qua');
        $this->assertSame(2, $counts['contacts']);
        $this->assertSame(1, $counts['guides']);
        $this->assertSame(1, $counts['scenarios']);
        $this->assertSame(1, $counts['alerts']);
        $this->assertSame(1, $counts['trips'], 'hành trình có owner_email sai phải bị bỏ qua');
        $this->assertSame(count(HazardTypeSeeder::FALLBACK), $counts['hazards']);
    }

    public function test_seeded_records_have_relations_and_relative_dates(): void
    {
        $this->useFixtures();
        $this->seed(DatabaseSeeder::class);

        $zone = Zone::where('code', 'vinh-nha-trang')->firstOrFail();
        $this->assertSame('khanh-hoa', $zone->parent->code);
        $this->assertSame('KH', $zone->province->code);
        $this->assertTrue($zone->containsPoint(12.22, 109.24));

        $alert = Alert::where('code', 'AL-SIM-0001')->with('zones', 'translations')->firstOrFail();
        $this->assertEqualsCanonicalizing(['khanh-hoa', 'vinh-nha-trang'], $alert->zones->pluck('code')->all());
        $this->assertSame(now()->toDateString().' 06:00', $alert->starts_at->format('Y-m-d H:i'));
        $this->assertSame(now()->addDays(2)->toDateString().' 18:00', $alert->ends_at->format('Y-m-d H:i'));
        $this->assertTrue($alert->isLive());
        app()->setLocale('en');
        $this->assertSame('[SIMULATION] Typhoon No. 3', $alert->headline);

        $trip = Trip::where('code', 'TR-SEED-01')->with('legs', 'members')->firstOrFail();
        $this->assertSame('traveler@dukhach.test', $trip->user->email);
        $this->assertSame(now()->toDateString(), $trip->start_date->toDateString());
        $this->assertCount(2, $trip->legs);
        $this->assertSame('vinh-nha-trang', $trip->legs[0]->destination->slug);
        $this->assertCount(2, $trip->members);
        $this->assertSame(40, strlen($trip->share_token));

        $guide = ResponseGuide::where('slug', 'ung-pho-bao')->firstOrFail();
        $this->assertSame(['Follow forecasts'], $guide->translate('en')->before_steps);
        $this->assertSame(['Ban Chỉ đạo quốc gia về PCTT'], $guide->translate('vi')->sources);

        $this->assertSame('Typhoon landfall in South Central Coast', SimulationScenario::firstOrFail()->localized('name', 'en'));
        $this->assertSame('Nha Trang Bay', Destination::firstOrFail()->translate('en')->name);
        $this->assertNull(EmergencyContact::where('phone', '113')->value('province_id'));
    }

    public function test_seeders_run_when_json_files_are_missing(): void
    {
        $this->useFixtures(sys_get_temp_dir().'/dukhach-empty-'.uniqid());

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(4, Role::count());
        $this->assertSame(count(HazardTypeSeeder::FALLBACK), HazardType::count());
        $this->assertSame(0, Zone::count());
        $this->assertSame(10, User::count());
    }

    public function test_demo_accounts_use_env_password_and_roles(): void
    {
        $this->useFixtures();
        config(['dukhach.seed_demo_password' => 'DuKhach@2026']);
        $this->seed(DatabaseSeeder::class);

        $expected = ['admin' => 'admin', 'agency' => 'agency', 'partner' => 'partner', 'traveler' => 'traveler'];
        foreach ($expected as $local => $role) {
            $user = User::where('email', "{$local}@dukhach.test")->firstOrFail();
            $this->assertSame($role, $user->role->code);
            $this->assertTrue(Auth::validate(['email' => $user->email, 'password' => 'DuKhach@2026']));
        }
        $this->assertSame('KH', User::where('email', 'agency@dukhach.test')->first()->province->code);
        for ($i = 1; $i <= 6; $i++) {
            $this->assertDatabaseHas('users', ['email' => "traveler{$i}@dukhach.test"]);
        }
    }

    public function test_checkin_seeder_creates_open_need_help_in_khanh_hoa_and_is_idempotent(): void
    {
        $this->useFixtures();
        $this->seed(DatabaseSeeder::class);
        \App\Models\SafetyCheckin::query()->update(['response_status' => 'closed']);
        $this->seed(DatabaseSeeder::class);

        $kh = \App\Models\Province::where('code', 'KH')->value('id');
        $needHelp = \App\Models\SafetyCheckin::query()->inProvince($kh)
            ->where('status', 'need_help')->where('response_status', 'open')->get();
        $this->assertCount(3, $needHelp, 'chạy lại seeder đặt lại 3 check-in cần hỗ trợ đang mở, không nhân bản');
        $this->assertTrue($needHelp->every(fn ($c) => $c->checked_at->gt(now()->subDay())));
        // Bộ dữ liệu mẫu nhỏ chỉ có TR-SEED-01: thêm 1 check-in "an toàn", các mã khác bỏ qua
        $this->assertSame(4, \App\Models\SafetyCheckin::count());
    }

    private function counts(): array
    {
        return [
            'users' => User::count(),
            'zones' => Zone::count(),
            'destinations' => Destination::count(),
            'contacts' => EmergencyContact::count(),
            'guides' => ResponseGuide::count(),
            'scenarios' => SimulationScenario::count(),
            'alerts' => Alert::count(),
            'trips' => Trip::count(),
            'legs' => \App\Models\TripLeg::count(),
            'members' => \App\Models\TripMember::count(),
            'hazards' => HazardType::count(),
            'translations' => \App\Models\AlertTranslation::count() + \App\Models\DestinationTranslation::count(),
            'checkins' => \App\Models\SafetyCheckin::count(),
        ];
    }
}
