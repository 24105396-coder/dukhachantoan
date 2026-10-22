<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Sao lưu CSDL MySQL bằng mysqldump vào storage/app/backups, giữ lại N bản gần nhất.
 * Khôi phục: mysql -u... -p... disanso < storage/app/backups/<file>.sql
 */
class BackupDatabase extends Command
{
    protected $signature = 'app:backup-db {--keep=7 : Số bản sao lưu giữ lại}';
    protected $description = 'Dump the MySQL database to storage/app/backups';

    /** Tệp chứa băm mật khẩu + dữ liệu cá nhân: chỉ chủ sở hữu (www-data) đọc/ghi được. */
    private const FILE_MODE = 0600;
    private const DIR_MODE = 0700;

    public function handle(): int
    {
        // umask 0077 bất kể tiến trình cha (docker exec có umask 0000) → tệp/thư mục mới không cho group/other
        $previousUmask = umask(0077);
        try {
            return $this->backup();
        } finally {
            umask($previousUmask);
        }
    }

    private function backup(): int
    {
        $dir = storage_path('app/backups');
        if (! is_dir($dir)) {
            mkdir($dir, self::DIR_MODE, true);
        }
        chmod($dir, self::DIR_MODE);
        $file = $dir.'/disanso_'.now()->format('Ymd_His').'.sql';
        $cfg = config('database.connections.mysql');

        $process = new Process([
            'mysqldump', '--single-transaction', '--routines', '--no-tablespaces', '--default-character-set=utf8mb4',
            '-h', $cfg['host'], '-P', (string) $cfg['port'], '-u', $cfg['username'], $cfg['database'],
        ], null, ['MYSQL_PWD' => $cfg['password']]);
        $process->setTimeout(600)->run();

        if (! $process->isSuccessful()) {
            Log::error('Database backup failed', ['error' => $process->getErrorOutput()]);
            $this->error('Backup failed: '.$process->getErrorOutput());

            return self::FAILURE;
        }
        file_put_contents($file, $process->getOutput());
        chmod($file, self::FILE_MODE);

        $this->rotate($dir);

        $size = round(filesize($file) / 1024, 1);
        Log::info('Database backup completed', ['file' => basename($file), 'size_kb' => $size]);
        $this->info(now()->toDateTimeString().' backup OK: '.basename($file)." ({$size} KB)");

        return self::SUCCESS;
    }

    /** Chỉ giữ N bản mới nhất; siết quyền cả các bản cũ tạo trước bản vá (0666/0644). */
    private function rotate(string $dir): void
    {
        $files = glob($dir.'/disanso_*.sql') ?: [];
        rsort($files);
        foreach (array_slice($files, (int) $this->option('keep')) as $old) {
            unlink($old);
        }
        foreach (array_slice($files, 0, (int) $this->option('keep')) as $kept) {
            @chmod($kept, self::FILE_MODE);
        }
    }
}
