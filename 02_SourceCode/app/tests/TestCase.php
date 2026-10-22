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

    protected function manager(): User
    {
        return User::factory()->manager()->create();
    }

    protected function contributor(): User
    {
        return User::factory()->contributor()->create();
    }

    protected function member(): User
    {
        return User::factory()->member()->create();
    }
}
