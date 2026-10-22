<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    private const ROLE_NAMES = [
        Role::ADMIN => 'Quản trị hệ thống',
        Role::MANAGER => 'Quản lý nghiệp vụ',
        Role::CONTRIBUTOR => 'Đơn vị quản lý di tích / nhà cung cấp tour',
        Role::MEMBER => 'Thành viên',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => fn () => self::roleId(Role::MEMBER),
            'locale' => 'vi',
            'theme' => 'light',
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public static function roleId(string $code): int
    {
        return Role::firstOrCreate(['code' => $code], ['name' => self::ROLE_NAMES[$code] ?? $code])->id;
    }

    public function withRole(string $code): static
    {
        return $this->state(fn () => ['role_id' => self::roleId($code)]);
    }

    public function admin(): static
    {
        return $this->withRole(Role::ADMIN);
    }

    public function manager(): static
    {
        return $this->withRole(Role::MANAGER);
    }

    public function contributor(): static
    {
        return $this->withRole(Role::CONTRIBUTOR)->state(['organization' => 'Ban quản lý di tích thử nghiệm']);
    }

    public function member(): static
    {
        return $this->withRole(Role::MEMBER);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function unverified(): static
    {
        return $this->state(['email_verified_at' => null]);
    }
}
