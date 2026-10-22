<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsClient;
use App\Services\RiskService;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** Bộ đệm bản dịch giao diện theo locale trong một tiến trình. */
    private static array $translationCache = [];

    public function share(Request $request): array
    {
        // Dùng closure để giá trị phụ thuộc locale được tính sau khi SetLocale chạy
        return array_merge(parent::share($request), [
            'auth' => fn () => ['user' => $this->sharedUser($request)],
            'locale' => fn () => app()->getLocale(),
            'locales' => fn () => collect(config('dukhach.locales'))
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values()->all(),
            'translations' => fn () => self::translations(app()->getLocale()),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'app' => fn () => [
                'name' => 'DuKhachAnToan',
                'course' => config('dukhach.academic.course'),
                'courseCode' => config('dukhach.academic.course_code'),
                'team' => config('dukhach.academic.team'),
                'notice' => __('messages.app.notice'),
                'analyticsUp' => app(AnalyticsClient::class)->isUp(),
                'simulationMode' => (bool) config('dukhach.simulation_mode'),
            ],
            'risk' => fn () => app(RiskService::class)->nationalSummary(),
            'map' => fn () => ['provider' => self::mapProvider()],
            'currentUrlOtherLocale' => fn () => self::otherLocaleUrl($request),
        ]);
    }

    /** Nhà cung cấp bản đồ nền; giá trị lạ trong env quy về osm. */
    public static function mapProvider(): string
    {
        $provider = strtolower((string) config('dukhach.map.provider', 'osm'));

        return in_array($provider, ['google', 'osm'], true) ? $provider : 'osm';
    }

    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        $user->loadMissing('role', 'province');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'organization' => $user->organization,
            'role' => $user->role?->code,
            'locale' => $user->locale,
            'theme' => $user->theme,
            'province' => $user->province?->brief(),
            'unread_notifications' => $this->unreadCount($user),
        ];
    }

    private function unreadCount($user): int
    {
        try {
            return $user->unreadNotifications()->count();
        } catch (Throwable $e) {
            report($e);

            return 0;
        }
    }

    /** Đọc lang/{locale}.json do frontend quản lý; thiếu tệp thì trả mảng rỗng. */
    public static function translations(string $locale): array
    {
        if (! array_key_exists($locale, self::$translationCache)) {
            $file = lang_path($locale.'.json');
            $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
            self::$translationCache[$locale] = is_array($data) ? $data : [];
        }

        return self::$translationCache[$locale];
    }

    /** Cùng trang nhưng đổi tiền tố locale (vi ↔ en). */
    public static function otherLocaleUrl(Request $request): string
    {
        $current = app()->getLocale();
        $other = $current === 'vi' ? 'en' : 'vi';
        $path = ltrim($request->path(), '/');

        if (preg_match('#^(vi|en)(/|$)#', $path)) {
            $path = preg_replace('#^(vi|en)#', $other, $path, 1);
        } else {
            $path = $other;
        }
        $query = $request->getQueryString();

        return url($path).($query ? '?'.$query : '');
    }
}
