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
        Role::AGENCY => 'Cơ quan hỗ trợ du khách',
        Role::PARTNER => 'Đơn vị lữ hành',
        Role::TRAVELER => 'Du khách',
    ];

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role_id' => fn () => self::roleId(Role::TRAVELER),
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

    /** Cơ quan hỗ trợ; mặc định phụ trách Khánh Hòa, truyền null để phụ trách toàn quốc. */
    public function agency(?string $provinceCode = 'KH'): static
    {
        return $this->withRole(Role::AGENCY)->state(fn () => [
            'organization' => 'Trung tâm hỗ trợ du khách (thử nghiệm)',
            'province_id' => $provinceCode ? FactoryHelpers::provinceId($provinceCode) : null,
        ]);
    }

    public function partner(): static
    {
        return $this->withRole(Role::PARTNER)->state(['organization' => 'Công ty lữ hành thử nghiệm']);
    }

    public function traveler(): static
    {
        return $this->withRole(Role::TRAVELER);
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
