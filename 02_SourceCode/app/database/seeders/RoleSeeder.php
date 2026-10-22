<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public const ROLES = [
        Role::ADMIN => ['Quản trị hệ thống', 'Toàn quyền, quản lý người dùng, xem nhật ký'],
        Role::MANAGER => ['Quản lý nghiệp vụ', 'Quản lý nội dung di sản, tuyến tham quan, kiểm duyệt đóng góp'],
        Role::CONTRIBUTOR => ['Đơn vị quản lý di tích / nhà cung cấp tour', 'Quản lý tuyến và khung giờ của mình, gửi đóng góp'],
        Role::MEMBER => ['Thành viên', 'Đặt lịch, thanh toán, yêu thích, gửi đóng góp'],
    ];

    public function run(): void
    {
        foreach (self::ROLES as $code => [$name, $description]) {
            Role::updateOrCreate(['code' => $code], ['name' => $name, 'description' => $description]);
        }
    }
}
