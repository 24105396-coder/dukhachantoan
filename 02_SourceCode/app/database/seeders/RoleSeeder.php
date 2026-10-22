<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public const ROLES = [
        Role::ADMIN => ['Quản trị hệ thống', 'Toàn quyền: người dùng, nhật ký, sao lưu, nguồn dữ liệu, mô phỏng'],
        Role::AGENCY => ['Cơ quan hỗ trợ du khách', 'Dashboard tỉnh mình, phát cảnh báo, phản hồi check-in, danh bạ, hướng dẫn, duyệt sự cố'],
        Role::PARTNER => ['Đơn vị lữ hành', 'Đăng ký và quản lý hành trình cho đoàn khách, check-in thay đoàn'],
        Role::TRAVELER => ['Du khách', 'Hành trình của mình, thông báo, check-in, báo sự cố'],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $code => [$name, $description]) {
            Role::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $description]);
        }
    }
}
