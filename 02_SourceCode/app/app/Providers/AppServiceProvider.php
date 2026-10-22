<?php

namespace App\Providers;

use App\Models\Artifact;
use App\Models\Booking;
use App\Models\Contribution;
use App\Models\HeritageSite;
use App\Models\HistoricalFigure;
use App\Models\Interpretation;
use App\Models\MediaAsset;
use App\Models\MediaCaption;
use App\Models\Period;
use App\Models\PeriodTranslation;
use App\Models\Province;
use App\Models\Payment;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Models\TimelineEvent;
use App\Models\TimelineEventTranslation;
use App\Models\TourRoute;
use App\Models\TourRouteStop;
use App\Models\TourRouteTranslation;
use App\Models\TourSlot;
use App\Models\User;
use App\Observers\FlushPageCache;
use App\Policies\BookingPolicy;
use App\Policies\ContributionPolicy;
use App\Policies\TourRoutePolicy;
use App\Services\Payment\PaymentGateway;
use App\Services\Payment\SandboxGateway;
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
    /** Giá trị mẫu từng nằm trong kho mã, không bao giờ được dùng làm khóa thật. */
    public const INSECURE_PAYMENT_SECRETS = ['sandbox-secret-change-me', 'changeme', 'secret'];

    public function register(): void
    {
        // Chỉ cổng mô phỏng được hiện thực; cấu hình cổng khác thì không bind (route sandbox cũng tắt)
        if (config('disanso.payment.gateway') === 'sandbox') {
            $this->app->bind(PaymentGateway::class, SandboxGateway::class);
        }
        // Bảng periods nhỏ, nạp một lần cho mỗi request/lệnh seed
        $this->app->scoped(\App\Services\PeriodResolver::class);
        $this->app->singleton(\App\Services\PageCache::class);
    }

    public function boot(): void
    {
        // Cột *_type lưu alias ngắn thay vì tên lớp, tránh lộ cấu trúc mã và gọn dữ liệu
        Relation::enforceMorphMap([
            'site' => HeritageSite::class,
            'artifact' => Artifact::class,
            'figure' => HistoricalFigure::class,
            'route' => TourRoute::class,
            'user' => User::class,
            // Alias bổ sung cho nhật ký kiểm toán và tệp đa phương tiện
            'booking' => Booking::class,
            'payment' => Payment::class,
            'contribution' => Contribution::class,
            'tag' => Tag::class,
            'media' => MediaAsset::class,
            'slot' => TourSlot::class,
        ]);

        // Resource tự bọc "data" chỉ ở API; Inertia nhận mảng phẳng
        JsonResource::withoutWrapping();

        $this->ensurePaymentSecret();

        $this->registerAuthorization();
        $this->registerRateLimiters();
        $this->registerPageCacheInvalidation();

        // Không dùng Vite::prefetch: nó tải trước mọi chunk động (model-viewer, Chart.js, trang quản trị)
        // sau sự kiện load, làm chậm LCP mobile; Inertia tự nạp chunk trang kế tiếp khi điều hướng.
        Vite::useCspNonce();
    }

    /** Production + cổng sandbox mà khóa HMAC rỗng hoặc còn giá trị mẫu công khai → dừng ngay. */
    public function ensurePaymentSecret(): void
    {
        // package:discover chạy lúc build image (composer dump-autoload), khi chưa có biến môi trường runtime
        if (! $this->app->isProduction() || config('disanso.payment.gateway') !== 'sandbox'
            || $this->app->runningConsoleCommand('package:discover')) {
            return;
        }
        $secret = (string) config('disanso.payment.sandbox_secret');
        if ($secret === '' || in_array($secret, self::INSECURE_PAYMENT_SECRETS, true)) {
            throw new \RuntimeException('PAYMENT_SANDBOX_SECRET must be set to a random value in production.');
        }
    }

    /** Mọi model hiển thị trên trang công khai đã đệm: lưu/xóa → tăng phiên bản PageCache. */
    private function registerPageCacheInvalidation(): void
    {
        foreach ([
            HeritageSite::class, Artifact::class, HistoricalFigure::class, Interpretation::class,
            MediaAsset::class, MediaCaption::class, Tag::class, TagTranslation::class,
            TourRoute::class, TourRouteTranslation::class, TourRouteStop::class, TourSlot::class,
            TimelineEvent::class, TimelineEventTranslation::class, Period::class, PeriodTranslation::class, Province::class,
        ] as $model) {
            $model::observe(FlushPageCache::class);
        }
    }

    private function registerAuthorization(): void
    {
        Gate::policy(TourRoute::class, TourRoutePolicy::class);
        Gate::policy(Booking::class, BookingPolicy::class);
        Gate::policy(Contribution::class, ContributionPolicy::class);

        Gate::define('manage-content', fn (User $user) => $user->isStaff());
        Gate::define('manage-users', fn (User $user) => $user->isAdmin());
        Gate::define('view-audit', fn (User $user) => $user->isAdmin());
    }

    private function registerRateLimiters(): void
    {
        // Chống dò mật khẩu: 5 lần/phút cho mỗi cặp email + IP, thêm 5 lần/phút theo riêng email
        // (độc lập IP) để đổi IP/X-Forwarded-For không vượt được giới hạn.
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return [
                Limit::perMinute(5)->by('email-ip:'.$email.'|'.$request->ip()),
                Limit::perMinute(5)->by('email:'.$email),
            ];
        });

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)->by($request->ip()));
    }
}
