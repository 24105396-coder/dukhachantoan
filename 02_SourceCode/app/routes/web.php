<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Account\DashboardController as AccountDashboardController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ArtifactController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ContributionController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FigureController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\PaymentSandboxController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\TagController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\TourController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $locale = in_array(session('locale'), ['vi', 'en'], true) ? session('locale') : 'vi';

    return redirect('/'.$locale);
})->name('root');

Route::prefix('{locale}')->where(['locale' => 'vi|en'])->middleware('locale')->group(function () {
    // ---------- Công khai ----------
    Route::get('/', HomeController::class)->name('home');
    Route::get('di-san', [SiteController::class, 'index'])->name('sites.index');
    Route::get('di-san/{site:slug}', [SiteController::class, 'show'])->name('sites.show');
    Route::get('hien-vat', [ArtifactController::class, 'index'])->name('artifacts.index');
    Route::get('hien-vat/{artifact:slug}', [ArtifactController::class, 'show'])->name('artifacts.show');
    Route::get('nhan-vat', [FigureController::class, 'index'])->name('figures.index');
    Route::get('nhan-vat/{figure:slug}', [FigureController::class, 'show'])->name('figures.show');
    Route::get('dong-thoi-gian', [TimelineController::class, 'index'])->name('timeline.index');
    Route::get('ban-do', [MapController::class, 'index'])->name('map.index');
    Route::get('tuyen-tham-quan', [TourController::class, 'index'])->name('tours.index');
    Route::get('tuyen-tham-quan/{route:slug}', [TourController::class, 'show'])->name('tours.show');
    Route::get('tim-kiem', [SearchController::class, 'index'])->name('search.index');
    Route::get('the', [TagController::class, 'index'])->name('tags.index');
    Route::get('the/{tag:slug}', [TagController::class, 'show'])->name('tags.show');
    Route::get('gioi-thieu', AboutController::class)->name('about');

    // ---------- Xác thực ----------
    Route::middleware('guest')->group(function () {
        Route::get('dang-nhap', [LoginController::class, 'create'])->name('login');
        Route::post('dang-nhap', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::get('dang-ky', [RegisterController::class, 'create'])->name('register');
        Route::post('dang-ky', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
    });
    Route::post('dang-xuat', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    // ---------- Tài khoản, đặt lịch, thanh toán ----------
    Route::middleware('auth')->group(function () {
        Route::get('tai-khoan', AccountDashboardController::class)->name('account.dashboard');
        Route::get('tai-khoan/ho-so', [ProfileController::class, 'edit'])->name('account.profile');
        Route::patch('tai-khoan/ho-so', [ProfileController::class, 'update'])->name('account.profile.update');
        Route::patch('tai-khoan/mat-khau', [ProfileController::class, 'password'])->name('account.password.update');

        Route::post('yeu-thich', [FavoriteController::class, 'toggle'])->name('favorites.toggle');

        Route::get('dong-gop/moi', [ContributionController::class, 'create'])->name('contributions.create');
        Route::post('dong-gop', [ContributionController::class, 'store'])->middleware('throttle:10,1')->name('contributions.store');
        Route::get('dong-gop/{contribution}', [ContributionController::class, 'show'])->whereNumber('contribution')->name('contributions.show');
        // Tệp đính kèm: id mã hóa (UrlToken) + chữ ký 60 phút (signed:relative phủ path + query, không phụ thuộc host/cổng) + Policy view
        Route::get('dong-gop/tep-dinh-kem/{token}', [ContributionController::class, 'attachment'])
            ->where('token', '[A-Za-z0-9_\-]+')->middleware('signed:relative')->name('contributions.attachment');

        Route::post('dat-lich/{slot}', [BookingController::class, 'store'])->whereNumber('slot')->middleware('throttle:20,1')->name('bookings.store');
        // Tra cứu đặt lịch bằng id mã hóa (liên kết gửi email/trang xác nhận); token hỏng → 404, không phải chủ → 403
        Route::get('dat-lich/tra-cuu/{token}', [BookingController::class, 'lookup'])
            ->where('token', '[A-Za-z0-9_\-]+')->name('bookings.lookup');
        Route::get('dat-lich/{booking:code}', [BookingController::class, 'show'])->name('bookings.show');
        Route::post('dat-lich/{booking:code}/thanh-toan', [BookingController::class, 'pay'])->name('bookings.pay');
        Route::post('dat-lich/{booking:code}/huy', [BookingController::class, 'cancel'])->name('bookings.cancel');

        // Cổng mô phỏng chỉ tồn tại khi cấu hình PAYMENT_GATEWAY=sandbox (cổng khác → 404)
        if (config('disanso.payment.gateway') === 'sandbox') {
            Route::get('cong-thanh-toan/{payment:txn_ref}', [PaymentSandboxController::class, 'show'])->name('payments.sandbox');
            Route::post('cong-thanh-toan/{payment:txn_ref}/ket-qua', [PaymentSandboxController::class, 'result'])->name('payments.sandbox.result');
        }
    });

    // ---------- Quản trị ----------
    // auth trước role: khách vãng lai được chuyển tới đăng nhập (giữ url.intended), đã đăng nhập sai vai trò → 403
    Route::prefix('quan-tri')->name('admin.')->middleware(['auth', 'role:admin,manager,contributor'])->group(function () {
        // Contributor: chỉ tuyến của mình (Policy) và đặt lịch thuộc tuyến mình
        Route::get('tuyen-tham-quan', [Admin\TourController::class, 'index'])->name('tours.index');
        Route::get('tuyen-tham-quan/moi', [Admin\TourController::class, 'create'])->name('tours.create');
        Route::post('tuyen-tham-quan', [Admin\TourController::class, 'store'])->name('tours.store');
        Route::get('tuyen-tham-quan/{route}/sua', [Admin\TourController::class, 'edit'])->name('tours.edit');
        Route::put('tuyen-tham-quan/{route}', [Admin\TourController::class, 'update'])->name('tours.update');
        Route::delete('tuyen-tham-quan/{route}', [Admin\TourController::class, 'destroy'])->name('tours.destroy');
        Route::post('tuyen-tham-quan/{route}/khung-gio', [Admin\TourController::class, 'storeSlot'])->name('tours.slots.store');
        Route::delete('tuyen-tham-quan/{route}/khung-gio/{slot}', [Admin\TourController::class, 'destroySlot'])->name('tours.slots.destroy');
        Route::get('dat-lich', [Admin\BookingController::class, 'index'])->name('bookings.index');

        Route::middleware('role:admin,manager')->group(function () {
            Route::get('/', Admin\DashboardController::class)->name('dashboard');

            foreach ([
                ['di-san', 'sites', 'site', Admin\SiteController::class],
                ['hien-vat', 'artifacts', 'artifact', Admin\ArtifactController::class],
                ['nhan-vat', 'figures', 'figure', Admin\FigureController::class],
                ['the', 'tags', 'tag', Admin\TagController::class],
            ] as [$path, $name, $param, $controller]) {
                Route::get($path, [$controller, 'index'])->name("$name.index");
                Route::get("$path/moi", [$controller, 'create'])->name("$name.create");
                Route::post($path, [$controller, 'store'])->name("$name.store");
                Route::get("$path/{{$param}}/sua", [$controller, 'edit'])->name("$name.edit");
                Route::put("$path/{{$param}}", [$controller, 'update'])->name("$name.update");
                Route::delete("$path/{{$param}}", [$controller, 'destroy'])->name("$name.destroy");
            }

            Route::patch('dat-lich/{booking}/trang-thai', [Admin\BookingController::class, 'status'])->name('bookings.status');

            Route::get('kiem-duyet', [Admin\ContributionController::class, 'index'])->name('contributions.index');
            Route::get('kiem-duyet/{contribution}', [Admin\ContributionController::class, 'show'])->whereNumber('contribution')->name('contributions.show');
            Route::patch('kiem-duyet/{contribution}', [Admin\ContributionController::class, 'review'])->whereNumber('contribution')->name('contributions.review');

            Route::get('media', [Admin\MediaController::class, 'index'])->name('media.index');
            Route::post('media', [Admin\MediaController::class, 'store'])->name('media.store');
            Route::match(['put', 'patch'], 'media/{media}', [Admin\MediaController::class, 'update'])->whereNumber('media')->name('media.update');
            Route::delete('media/{media}', [Admin\MediaController::class, 'destroy'])->whereNumber('media')->name('media.destroy');

            Route::get('chat-luong-du-lieu', [Admin\QualityController::class, 'index'])->name('quality.index');
            Route::post('chat-luong-du-lieu/kiem-tra', [Admin\QualityController::class, 'run'])->name('quality.run');
            Route::get('thong-ke/xuat', Admin\ExportController::class)->name('export');

            Route::middleware('role:admin')->group(function () {
                Route::get('nguoi-dung', [Admin\UserController::class, 'index'])->name('users.index');
                Route::patch('nguoi-dung/{user}', [Admin\UserController::class, 'update'])->whereNumber('user')->name('users.update');
                Route::get('nhat-ky', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            });
        });
    });
});
