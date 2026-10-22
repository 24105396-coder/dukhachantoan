<?php

namespace Database\Seeders\Concerns;

use App\Support\RelativeDate;
use Illuminate\Support\Carbon;

/**
 * Đọc dữ liệu hạt giống JSON trong database/data (lược đồ và từ điển dữ liệu: 03_Database/TuDienDuLieu.md).
 * Tệp thiếu hoặc lỗi cú pháp → in cảnh báo và trả mảng rỗng để seeder vẫn chạy được khi dữ liệu chưa có.
 */
trait ReadsSeedJson
{
    /** Thư mục dữ liệu: database/data, hoặc config dukhach.seed_data_path (test dùng tệp mẫu). */
    protected function dataPath(string $file = ''): string
    {
        $base = rtrim((string) (config('dukhach.seed_data_path') ?: database_path('data')), '/');

        return $base.($file !== '' ? '/'.$file : '');
    }

    protected function hasJson(string $file): bool
    {
        return is_file($this->dataPath($file));
    }

    protected function readJson(string $file): array
    {
        $path = str_starts_with($file, '/') ? $file : $this->dataPath($file);
        if (! is_file($path)) {
            $this->warnSeed("Bỏ qua: chưa có database/data/".basename($path));

            return [];
        }
        $data = json_decode((string) file_get_contents($path), true);
        if (! is_array($data)) {
            $this->warnSeed('Bỏ qua: '.basename($path).' không phải JSON mảng hợp lệ ('.json_last_error_msg().')');

            return [];
        }

        return $data;
    }

    protected function warnSeed(string $message): void
    {
        if (isset($this->command)) {
            $this->command->warn('  '.static::class.': '.$message);
        }
    }

    /** {"vi": "...", "en": "..."} hoặc chuỗi → giá trị theo locale (chuỗi dùng cho mọi locale). */
    protected function pick(mixed $value, string $locale): mixed
    {
        if (is_array($value) && (array_key_exists('vi', $value) || array_key_exists('en', $value))) {
            return $value[$locale] ?? null;
        }

        return $value;
    }

    /**
     * Gom các khóa đa ngữ của một bản ghi thành {locale: {field: value}} để syncTranslations().
     *
     * @param  array<string, string>  $map  khóa JSON => cột bản dịch
     */
    protected function translationsFrom(array $row, array $map): array
    {
        $out = [];
        foreach (['vi', 'en'] as $locale) {
            foreach ($map as $jsonKey => $column) {
                $value = $this->pick($row[$jsonKey] ?? null, $locale);
                if ($value !== null && $value !== '' && $value !== []) {
                    $out[$locale][$column] = $value;
                }
            }
        }

        return $out;
    }

    protected function when(?string $value): ?Carbon
    {
        return RelativeDate::parse($value);
    }
}
