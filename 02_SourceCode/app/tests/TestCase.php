<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Không cần build Vite và không gọi mạng thật khi test
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    /** Cơ quan hỗ trợ phụ trách tỉnh $provinceCode (null = toàn quốc). */
    protected function agency(?string $provinceCode = 'KH'): User
    {
        return User::factory()->agency($provinceCode)->create();
    }

    protected function partner(): User
    {
        return User::factory()->partner()->create();
    }

    protected function traveler(): User
    {
        return User::factory()->traveler()->create();
    }
}
