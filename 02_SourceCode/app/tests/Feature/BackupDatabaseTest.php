<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    use RefreshDatabase;

    private string $storage;

    protected function setUp(): void
    {
        parent::setUp();
        // Thư mục storage tạm để không đụng storage/app/backups thật
        $this->storage = sys_get_temp_dir().'/disanso_backup_test_'.uniqid();
        File::ensureDirectoryExists($this->storage.'/app');
        $this->app->useStoragePath($this->storage);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storage);
        parent::tearDown();
    }

    public function test_backup_file_is_owner_only_even_with_permissive_umask(): void
    {
        $dir = $this->storage.'/app/backups';
        mkdir($dir, 0777);
        chmod($dir, 0777);
        // Bản cũ tạo trước bản vá với quyền 0666
        file_put_contents($dir.'/disanso_20000101_000000.sql', '-- old');
        chmod($dir.'/disanso_20000101_000000.sql', 0666);

        $previous = umask(0000); // giống docker compose exec
        try {
            $this->artisan('app:backup-db', ['--keep' => 5])->assertSuccessful();
        } finally {
            umask($previous);
        }

        clearstatcache();
        $this->assertSame('0700', substr(sprintf('%o', fileperms($dir)), -4));
        $files = glob($dir.'/disanso_*.sql');
        $this->assertCount(2, $files);
        foreach ($files as $file) {
            $this->assertSame('0600', substr(sprintf('%o', fileperms($file)), -4), basename($file));
        }
        $newest = max($files);
        $this->assertStringContainsString('CREATE TABLE', file_get_contents($newest));
    }
}
