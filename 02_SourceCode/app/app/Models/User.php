<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\RoutesNotifications;

/**
 * Người dùng 4 vai trò. Không dùng trait Notifiable vì bảng `notifications` là thông báo nghiệp vụ
 * của hệ thống (App\Models\Notification), không phải database notification của Laravel.
 */
class User extends Authenticatable
{
    use HasFactory, RoutesNotifications;

    protected $fillable = [
        'name', 'email', 'password', 'role_id', 'locale', 'theme', 'phone', 'nationality', 'organization',
        'province_id', 'emergency_contact_name', 'emergency_contact_phone', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /** Tỉnh cơ quan hỗ trợ phụ trách; null = toàn quốc. */
    public function province(): BelongsTo
    {
        return $this->belongsTo(Province::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    /** Hành trình đơn vị lữ hành đăng ký cho đoàn khách. */
    public function partnerTrips(): HasMany
    {
        return $this->hasMany(Trip::class, 'partner_id');
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function unreadNotifications(): HasMany
    {
        return $this->notifications()->where('channel', Notification::CHANNEL_INAPP)->whereNull('read_at')
            ->whereNotIn('status', [Notification::STATUS_CANCELLED, Notification::STATUS_FAILED]);
    }

    public function notificationPreference(): HasOne
    {
        return $this->hasOne(NotificationPreference::class);
    }

    public function checkins(): HasMany
    {
        return $this->hasMany(SafetyCheckin::class);
    }

    public function incidentReports(): HasMany
    {
        return $this->hasMany(IncidentReport::class);
    }

    public function hasRole(string ...$codes): bool
    {
        return in_array($this->role?->code, $codes, true);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::ADMIN);
    }

    public function isAgency(): bool
    {
        return $this->hasRole(Role::AGENCY);
    }

    public function isPartner(): bool
    {
        return $this->hasRole(Role::PARTNER);
    }

    public function isTraveler(): bool
    {
        return $this->hasRole(Role::TRAVELER);
    }

    /** Admin hoặc cơ quan hỗ trợ: được vào khu vực /quan-tri. */
    public function isStaff(): bool
    {
        return $this->hasRole(Role::ADMIN, Role::AGENCY);
    }

    /** Trang đích sau đăng nhập: cơ quan/quản trị → bảng điều khiển, còn lại → tài khoản. */
    public function homeRouteName(): string
    {
        return $this->isStaff() ? 'admin.dashboard' : 'account.edit';
    }

    /** Cơ quan có phạm vi tỉnh (admin và agency toàn quốc trả null). */
    public function scopedProvinceId(): ?int
    {
        return $this->isAgency() ? $this->province_id : null;
    }
}
