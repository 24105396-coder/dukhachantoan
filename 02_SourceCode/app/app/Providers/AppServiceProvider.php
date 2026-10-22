<?php

namespace App\Providers;

use App\Models\Alert;
use App\Models\AlertMatch;
use App\Models\AlertSource;
use App\Models\AlertTranslation;
use App\Models\Destination;
use App\Models\DestinationTranslation;
use App\Models\EmergencyContact;
use App\Models\EmergencyContactTranslation;
use App\Models\HazardType;
use App\Models\HazardTypeTranslation;
use App\Models\IncidentReport;
use App\Models\MediaAsset;
use App\Models\Notification;
use App\Models\Province;
use App\Models\ResponseGuide;
use App\Models\ResponseGuideTranslation;
use App\Models\SafetyCheckin;
use App\Models\SimulationRun;
use App\Models\SimulationScenario;
use App\Models\Trip;
use App\Models\TripLeg;
use App\Models\User;
use App\Models\Zone;
use App\Observers\FlushPageCache;
use App\Services\PageCache;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /** Alias morph theo dka-conventions; cột *_type lưu alias ngắn, không lộ tên lớp. */
    public const MORPH_MAP = [
        'zone' => Zone::class,
        'destination' => Destination::class,
        'trip' => Trip::class,
        'leg' => TripLeg::class,
        'alert' => Alert::class,
        'match' => AlertMatch::class,
        'notification' => Notification::class,
        'checkin' => SafetyCheckin::class,
        'incident' => IncidentReport::class,
        'contact' => EmergencyContact::class,
        'guide' => ResponseGuide::class,
        'scenario' => SimulationScenario::class,
        'user' => User::class,
        // Bổ sung cho nhật ký kiểm toán (subject của audit_logs): nguồn dữ liệu, lần chạy mô phỏng, tệp
        'source' => AlertSource::class,
        'run' => SimulationRun::class,
        'media' => MediaAsset::class,
    ];

    public function register(): void
    {
        $this->app->singleton(PageCache::class);
    }

    public function boot(): void
    {
        Relation::enforceMorphMap(self::MORPH_MAP);

        // Resource tự bọc "data" chỉ ở API; Inertia nhận mảng phẳng
        JsonResource::withoutWrapping();

        // Liên kết đặt lại mật khẩu theo ngôn ngữ của người dùng
        ResetPassword::createUrlUsing(fn (User $user, string $token) => localized_route('password.reset', [
            'locale' => $user->locale ?: 'vi', 'token' => $token, 'email' => $user->email,
        ]));

        $this->registerAuthorization();
        $this->registerRateLimiters();
        $this->registerPageCacheInvalidation();

        Vite::useCspNonce();
    }

    /** Dữ liệu hiển thị trên trang công khai đã đệm: lưu/xóa → tăng phiên bản PageCache. */
    private function registerPageCacheInvalidation(): void
    {
        foreach ([
            Zone::class, Destination::class, DestinationTranslation::class, HazardType::class, HazardTypeTranslation::class,
            Alert::class, AlertTranslation::class, EmergencyContact::class, EmergencyContactTranslation::class,
            ResponseGuide::class, ResponseGuideTranslation::class, Province::class, AlertSource::class,
        ] as $model) {
            $model::observe(FlushPageCache::class);
        }
    }

    private function registerAuthorization(): void
    {
        Gate::define('access-admin', fn (User $user) => $user->isStaff());
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());
        Gate::define('view-audit', fn (User $user) => $user->isAdmin());
        Gate::define('manage-backup', fn (User $user) => $user->isAdmin());
        Gate::define('run-simulation', fn (User $user) => $user->isAdmin());
    }

    private function registerRateLimiters(): void
    {
        // Chống dò mật khẩu: 5 lần/phút cho mỗi cặp email + IP, thêm 5 lần/phút theo riêng email
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('email-ip:'.$email.'|'.$request->ip()),
                Limit::perMinute(5)->by('email:'.$email),
            ];
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));

        // Báo an toàn qua liên kết chia sẻ (không đăng nhập): 10 lần/giờ cho mỗi token
        RateLimiter::for('checkin-token', fn (Request $request) => Limit::perHour(10)
            ->by('checkin:'.substr(hash('sha256', (string) $request->route('token')), 0, 16)));
    }
}
