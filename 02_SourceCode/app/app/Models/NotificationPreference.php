<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tùy chọn kênh nhận theo mức cảnh báo, giờ yên lặng, bản tin ngày. */
class NotificationPreference extends Model
{
    /** Mặc định: mức càng cao càng nhiều kênh; mức 4 luôn gửi mọi kênh. */
    public const DEFAULT_CHANNELS = [
        1 => ['inapp'],
        2 => ['inapp'],
        3 => ['inapp', 'email'],
        4 => ['inapp', 'email', 'sms'],
    ];

    protected $fillable = [
        'user_id', 'channels_level1', 'channels_level2', 'channels_level3', 'channels_level4', 'quiet_from', 'quiet_to',
        'digest_daily', 'language',
    ];

    protected function casts(): array
    {
        return [
            'channels_level1' => 'array',
            'channels_level2' => 'array',
            'channels_level3' => 'array',
            'channels_level4' => 'array',
            'digest_daily' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Kênh cho mức $level; mức 4 luôn bao gồm mọi kênh bất kể cấu hình. */
    public function channelsFor(int $level): array
    {
        if ($level >= 4) {
            return self::DEFAULT_CHANNELS[4];
        }
        $channels = $this->{'channels_level'.$level} ?? null;

        return is_array($channels) && $channels !== [] ? array_values(array_intersect($channels, Notification::CHANNELS))
            : self::DEFAULT_CHANNELS[$level] ?? ['inapp'];
    }

    /** Giá trị mặc định cho người dùng chưa lưu tùy chọn. */
    public static function defaults(): array
    {
        return [
            'channels_level1' => self::DEFAULT_CHANNELS[1],
            'channels_level2' => self::DEFAULT_CHANNELS[2],
            'channels_level3' => self::DEFAULT_CHANNELS[3],
            'channels_level4' => self::DEFAULT_CHANNELS[4],
            'quiet_from' => null,
            'quiet_to' => null,
            'digest_daily' => true,
            'language' => 'vi',
        ];
    }
}
