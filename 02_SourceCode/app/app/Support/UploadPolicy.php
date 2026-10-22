<?php

namespace App\Support;

use Closure;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

/**
 * Danh mục tệp được phép tải lên theo loại media: phần mở rộng phía client, MIME thật
 * (finfo) và đuôi lưu cố định. Tên tệp lưu luôn ngẫu nhiên, không dùng extension() đoán từ nội dung.
 */
final class UploadPolicy
{
    public const TYPES = [
        // Ảnh minh họa hướng dẫn/vùng và ảnh sự cố du khách gửi
        'image' => ['extensions' => 'jpg,jpeg,png,webp', 'mimetypes' => ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']],
        'document' => ['extensions' => 'pdf', 'mimetypes' => ['application/pdf' => 'pdf']],
    ];

    /** Loại media quản trị viên được tải lên thư viện. */
    public const MEDIA_TYPES = ['image', 'document'];

    /** Dung lượng tối đa ảnh sự cố (KB). */
    public const INCIDENT_PHOTO_MAX_KB = 5120;

    /** Luật validate cho trường tệp của loại media (loại lạ → luật của ảnh, chặt nhất). */
    public static function rules(string $type): array
    {
        $spec = self::TYPES[$type] ?? self::TYPES['image'];
        $rules = ['extensions:'.$spec['extensions']];
        if ($spec['mimetypes']) {
            $rules[] = 'mimetypes:'.implode(',', array_keys($spec['mimetypes']));
        }
        if (isset($spec['magic'])) {
            $rules[] = self::magicRule($spec['magic']);
        }

        return $rules;
    }

    /** Đuôi lưu trữ đã duyệt theo MIME thật; null nếu không khớp danh mục. */
    public static function extensionFor(string $type, UploadedFile $file): ?string
    {
        $spec = self::TYPES[$type] ?? null;
        if (! $spec) {
            return null;
        }

        return $spec['fixed'] ?? ($spec['mimetypes'][(string) $file->getMimeType()] ?? null);
    }

    /** Tên tệp ngẫu nhiên + đuôi cố định. */
    public static function randomName(string $extension): string
    {
        return Str::lower(Str::random(40)).'.'.$extension;
    }

    private static function magicRule(string $magic): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($magic) {
            if (! $value instanceof UploadedFile || ! $value->isValid()) {
                return;
            }
            $handle = @fopen($value->getRealPath(), 'rb');
            $head = $handle ? (string) fread($handle, strlen($magic)) : '';
            if ($handle) {
                fclose($handle);
            }
            if ($head !== $magic) {
                $fail('validation.mimetypes')->translate(['values' => 'model/gltf-binary (.glb)']);
            }
        };
    }
}
