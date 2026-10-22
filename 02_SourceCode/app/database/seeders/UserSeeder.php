<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $password = (string) config('disanso.seed_demo_password', 'DiSan@2026');
        $roles = Role::pluck('id', 'code');

        // Số điện thoại mô phỏng (đầu 09) để form đặt lịch điền sẵn contact_phone khi demo
        $accounts = [
            ['admin@disanso.test', 'Quản trị viên', Role::ADMIN, null, '0912000001'],
            ['manager@disanso.test', 'Quản lý nghiệp vụ', Role::MANAGER, null, '0912000002'],
            ['partner@disanso.test', 'Đối tác di tích', Role::CONTRIBUTOR, 'Ban quản lý di tích mẫu', '0912000003'],
            ['user@disanso.test', 'Khách tham quan', Role::MEMBER, null, '0912345678'],
        ];
        foreach ($accounts as [$email, $name, $role, $org, $phone]) {
            $this->upsertUser($email, $name, $roles[$role], $password, $org, $phone);
        }

        // Thành viên giả lập phục vụ thống kê; tên sinh bằng Faker tiếng Việt, cố định seed để chạy lại ổn định.
        $faker = Faker::create('vi_VN');
        $faker->seed(2026);
        for ($i = 1; $i <= 6; $i++) {
            $this->upsertUser("member{$i}@disanso.test", $faker->name(), $roles[Role::MEMBER], $password, null, '09'.$faker->numerify('########'));
        }
    }

    private function upsertUser(string $email, string $name, int $roleId, string $password, ?string $org, ?string $phone = null): void
    {
        $user = User::firstOrNew(['email' => $email]);
        $user->fill([
            'name' => $name,
            'role_id' => $roleId,
            'organization' => $org,
            'phone' => $phone ?? $user->phone,
            'locale' => 'vi',
            'theme' => $user->theme ?: 'light',
            'is_active' => true,
        ]);
        if (! $user->exists) {
            $user->password = $password;
            $user->email_verified_at = now();
        }
        $user->save();
    }
}
