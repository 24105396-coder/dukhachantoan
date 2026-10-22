<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/** In "yes" nếu CSDL đã có dữ liệu hạt giống, dùng bởi docker/entrypoint.sh. */
class IsSeeded extends Command
{
    protected $signature = 'app:is-seeded';
    protected $description = 'Check whether the database already contains seed data';

    public function handle(): int
    {
        $seeded = Schema::hasTable('roles') && \App\Models\Role::query()->exists();
        $this->output->write($seeded ? 'yes' : 'no');

        return self::SUCCESS;
    }
}
