<?php

namespace App\Http\Middleware;

use App\Services\AnalyticsClient;
use Illuminate\Http\Request;
use Inertia\Middleware;

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
            'locales' => fn () => collect(config('disanso.locales'))
                ->map(fn ($label, $code) => ['code' => $code, 'label' => $label])->values()->all(),
            'translations' => fn () => self::translations(app()->getLocale()),
            'flash' => fn () => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'app' => fn () => [
                'name' => 'DiSanSo',
                'course' => config('disanso.academic.course'),
                'courseCode' => config('disanso.academic.course_code'),
                'team' => config('disanso.academic.team'),
                'notice' => __('messages.app.notice'),
                'analyticsUp' => app(AnalyticsClient::class)->isUp(),
            ],
            'map' => fn () => ['provider' => self::mapProvider()],
            'currentUrlOtherLocale' => fn () => self::otherLocaleUrl($request),
        ]);
    }

    /** Nhà cung cấp bản đồ nền; giá trị lạ trong env quy về google để frontend không phải xử lý. */
    public static function mapProvider(): string
    {
        $provider = strtolower((string) config('disanso.map.provider', 'google'));

        return in_array($provider, ['google', 'osm'], true) ? $provider : 'google';
    }

    private function sharedUser(Request $request): ?array
    {
        $user = $request->user();
        if (! $user) {
            return null;
        }
        $user->loadMissing('role');

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'organization' => $user->organization,
            'role' => $user->role?->code,
            'locale' => $user->locale,
            'theme' => $user->theme,
        ];
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
