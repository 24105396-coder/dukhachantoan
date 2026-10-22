<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Models\Role;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

/**
 * Tài khoản mẫu theo dka-conventions; mật khẩu chung từ env SEED_DEMO_PASSWORD (mặc định DuKhach@2026).
 * Chạy lại không đổi mật khẩu của tài khoản đã có. Số điện thoại, tên là dữ liệu mô phỏng.
 */
class UserSeeder extends Seeder
{
    public const DOMAIN = 'dukhach.test';
    public const AGENCY_PROVINCE = 'KH';
    public const EXTRA_TRAVELERS = 6;

    public function run(): void
    {
        $password = (string) config('dukhach.seed_demo_password', 'DuKhach@2026');
        $roles = Role::pluck('id', 'code');
        $agencyProvince = Province::query()->where('code', self::AGENCY_PROVINCE)->value('id');

        $accounts = [
            ['admin', 'Quản trị viên', Role::ADMIN, null, '0912000001', null, 'vi'],
            ['agency', 'Trung tâm Hỗ trợ du khách Khánh Hòa', Role::AGENCY, 'Sở Du lịch Khánh Hòa (mô phỏng)', '0912000002', $agencyProvince, 'vi'],
            ['partner', 'Công ty Lữ hành An Việt (mô phỏng)', Role::PARTNER, 'Công ty Lữ hành An Việt (mô phỏng)', '0912000003', null, 'vi'],
            ['traveler', 'Alex Doe', Role::TRAVELER, null, '0912345678', null, 'en'],
        ];
        foreach ($accounts as [$local, $name, $role, $org, $phone, $provinceId, $locale]) {
            $this->upsertUser("{$local}@".self::DOMAIN, $name, $roles[$role], $password, [
                'organization' => $org,
                'phone' => $phone,
                'province_id' => $provinceId,
                'locale' => $locale,
                'nationality' => $local === 'traveler' ? 'GB' : 'VN',
            ]);
        }

        // Du khách mô phỏng có hành trình mẫu; tên Faker tiếng Việt cố định seed để chạy lại ổn định
        $faker = Faker::create('vi_VN');
        $faker->seed(2026);
        for ($i = 1; $i <= self::EXTRA_TRAVELERS; $i++) {
            $this->upsertUser("traveler{$i}@".self::DOMAIN, $faker->name(), $roles[Role::TRAVELER], $password, [
                'phone' => '09'.$faker->numerify('########'),
                'locale' => $i % 2 === 0 ? 'en' : 'vi',
                'nationality' => $i % 2 === 0 ? $faker->randomElement(['US', 'KR', 'JP', 'FR', 'AU']) : 'VN',
            ]);
        }
    }

    private function upsertUser(string $email, string $name, int $roleId, string $password, array $attributes): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill(array_merge([
            'name' => $name,
            'role_id' => $roleId,
            'theme' => $user->theme ?: 'light',
            'is_active' => true,
        ], $attributes));
        if (! $user->exists) {
            $user->password = $password;
            $user->email_verified_at = now();
        }
        $user->save();
    }
}
