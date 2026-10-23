<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\AlertController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\GuideController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\IncidentController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SharedTripController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\WeatherController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $locale = in_array(session('locale'), ['vi', 'en'], true) ? session('locale') : 'vi';

    return redirect('/'.$locale);
})->name('root');

Route::prefix('{locale}')->where(['locale' => 'vi|en'])->middleware('locale')->group(function () {
    // ---------- Công khai ----------
    Route::get('/', HomeController::class)->name('home');
    Route::get('gioi-thieu', AboutController::class)->name('about');
    Route::get('canh-bao', [AlertController::class, 'index'])->name('alerts.index');
    Route::get('canh-bao/{alert}', [AlertController::class, 'show'])->name('alerts.show');
    Route::get('ban-do', MapController::class)->name('map.index');
    Route::get('thoi-tiet/{zone?}', WeatherController::class)->name('weather.show');
    Route::get('danh-ba-khan-cap', ContactController::class)->name('contacts.index');
    Route::get('huong-dan-ung-pho', [GuideController::class, 'index'])->name('guides.index');
    Route::get('huong-dan-ung-pho/{guide:slug}', [GuideController::class, 'show'])->name('guides.show');
    // Chia sẻ hành trình theo token 40 ký tự; báo an toàn qua token giới hạn 10 lần/giờ/token
    Route::get('hanh-trinh/chia-se/{token}', [SharedTripController::class, 'show'])->name('trips.shared');
    Route::get('hanh-trinh/chia-se/{token}/bao-an-toan', [SharedTripController::class, 'checkinForm'])->name('trips.shared.checkin');
    Route::post('hanh-trinh/chia-se/{token}/bao-an-toan', [SharedTripController::class, 'checkin'])
        ->middleware('throttle:checkin-token')->name('trips.shared.checkin.store');

    // ---------- Xác thực ----------
    Route::middleware('guest')->group(function () {
        Route::get('dang-nhap', [LoginController::class, 'create'])->name('login');
        Route::post('dang-nhap', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::get('dang-ky', [RegisterController::class, 'create'])->name('register');
        Route::post('dang-ky', [RegisterController::class, 'store'])->middleware('throttle:6,1,register')->name('register.store');
        Route::get('quen-mat-khau', [PasswordResetController::class, 'create'])->name('password.request');
        Route::post('quen-mat-khau', [PasswordResetController::class, 'store'])->middleware('throttle:6,1,password-email')->name('password.email');
        Route::get('dat-lai-mat-khau/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('dat-lai-mat-khau', [PasswordResetController::class, 'update'])->middleware('throttle:6,1,password-update')->name('password.update');
    });
    Route::post('dang-xuat', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    // ---------- Người dùng đăng nhập (du khách, đơn vị lữ hành; cơ quan/admin cũng dùng được) ----------
    Route::middleware('auth')->group(function () {
        Route::get('tai-khoan', [ProfileController::class, 'edit'])->name('account.edit');
        Route::put('tai-khoan', [ProfileController::class, 'update'])->name('account.update');
        Route::patch('tai-khoan/giao-dien', [ProfileController::class, 'theme'])->name('account.theme');
        Route::patch('tai-khoan/mat-khau', [ProfileController::class, 'password'])->name('account.password.update');
        // Liên kết cũ (menu tài khoản bản kế thừa) → trang tài khoản mới
        Route::get('tai-khoan/ho-so', fn () => redirect(localized_route('account.edit')))->name('account.profile');

        Route::get('hanh-trinh', [TripController::class, 'index'])->name('trips.index');
        Route::get('hanh-trinh/tao', [TripController::class, 'create'])->name('trips.create');
        Route::post('hanh-trinh/tao', [TripController::class, 'store'])->middleware('throttle:20,1,trips-store')->name('trips.store');
        Route::get('hanh-trinh/{trip}', [TripController::class, 'show'])->name('trips.show');
        Route::get('hanh-trinh/{trip}/sua', [TripController::class, 'edit'])->name('trips.edit');
        Route::put('hanh-trinh/{trip}/sua', [TripController::class, 'update'])->name('trips.update');
        Route::post('hanh-trinh/{trip}/huy', [TripController::class, 'cancel'])->name('trips.cancel');
        Route::post('hanh-trinh/{trip}/bao-an-toan', [TripController::class, 'checkin'])->middleware('throttle:30,60,trips-checkin')->name('trips.checkin');
        Route::post('hanh-trinh/{trip}/xac-nhan/{match}', [TripController::class, 'acknowledge'])->whereNumber('match')->name('trips.acknowledge');
        Route::get('hanh-trinh/{trip}/lich.ics', [TripController::class, 'ical'])->name('trips.ical');

        Route::get('thong-bao', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('thong-bao/doc-tat-ca', [NotificationController::class, 'readAll'])->name('notifications.read_all');
        Route::post('thong-bao/{notification}/da-doc', [NotificationController::class, 'read'])->whereNumber('notification')->name('notifications.read');

        Route::get('bao-su-co', [IncidentController::class, 'create'])->name('incidents.create');
        Route::post('bao-su-co', [IncidentController::class, 'store'])->middleware('throttle:10,60,incidents-store')->name('incidents.store');
    });

    // ---------- Quản trị: cơ quan hỗ trợ + quản trị hệ thống ----------
    // auth trước role: khách được chuyển tới đăng nhập (giữ url.intended), sai vai trò → 403
    Route::prefix('quan-tri')->name('admin.')->middleware(['auth', 'role:agency,admin'])->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::get('canh-bao', [Admin\AlertController::class, 'index'])->name('alerts.index');
        Route::get('canh-bao/tao', [Admin\AlertController::class, 'create'])->name('alerts.create');
        Route::post('canh-bao/tao', [Admin\AlertController::class, 'store'])->name('alerts.store');
        Route::get('canh-bao/{alert}', [Admin\AlertController::class, 'show'])->name('alerts.show');
        Route::get('canh-bao/{alert}/sua', [Admin\AlertController::class, 'edit'])->name('alerts.edit');
        Route::put('canh-bao/{alert}/sua', [Admin\AlertController::class, 'update'])->name('alerts.update');
        Route::post('canh-bao/{alert}/trang-thai', [Admin\AlertController::class, 'transition'])->name('alerts.transition');
        // URL hành động frontend gọi (frontend_backend_2): tạo/sửa không hậu tố, nút trạng thái riêng
        Route::post('canh-bao', [Admin\AlertController::class, 'store'])->name('alerts.store.short');
        Route::put('canh-bao/{alert}', [Admin\AlertController::class, 'update'])->name('alerts.update.short');
        Route::post('canh-bao/{alert}/phat-hanh', [Admin\AlertController::class, 'publish'])->name('alerts.publish');
        Route::post('canh-bao/{alert}/het-hieu-luc', [Admin\AlertController::class, 'expire'])->name('alerts.expire');
        Route::post('canh-bao/{alert}/huy', [Admin\AlertController::class, 'cancel'])->name('alerts.cancel');

        Route::get('hanh-trinh', [Admin\TripController::class, 'index'])->name('trips.index');
        Route::get('hanh-trinh/{trip}', [Admin\TripController::class, 'show'])->name('trips.show');

        Route::get('an-toan', [Admin\CheckinController::class, 'index'])->name('checkins.index');
        Route::post('an-toan/{checkin}/phan-hoi', [Admin\CheckinController::class, 'respond'])->whereNumber('checkin')->name('checkins.respond');

        Route::get('thong-bao', [Admin\NotificationController::class, 'index'])->name('notifications.index');
        Route::post('thong-bao/{notification}/gui-lai', [Admin\NotificationController::class, 'resend'])->whereNumber('notification')->name('notifications.resend');
        Route::post('thong-bao/phat-thu-cong', [Admin\NotificationController::class, 'broadcast'])->middleware('throttle:10,1,notifications-broadcast')->name('notifications.broadcast');
        Route::post('thong-bao/phat', [Admin\NotificationController::class, 'broadcast'])->middleware('throttle:10,1,notifications-broadcast')->name('notifications.broadcast.short');
        Route::post('thong-bao/gui-lai', [Admin\NotificationController::class, 'resendById'])->name('notifications.resend.body');

        Route::get('danh-ba', [Admin\ContactController::class, 'index'])->name('contacts.index');
        Route::get('danh-ba/tao', [Admin\ContactController::class, 'create'])->name('contacts.create');
        Route::post('danh-ba/tao', [Admin\ContactController::class, 'store'])->name('contacts.store');
        Route::post('danh-ba', [Admin\ContactController::class, 'store'])->name('contacts.store.short');
        Route::put('danh-ba/{contact}', [Admin\ContactController::class, 'update'])->whereNumber('contact')->name('contacts.update.short');
        Route::get('danh-ba/{contact}/sua', [Admin\ContactController::class, 'edit'])->whereNumber('contact')->name('contacts.edit');
        Route::put('danh-ba/{contact}/sua', [Admin\ContactController::class, 'update'])->whereNumber('contact')->name('contacts.update');
        Route::post('danh-ba/{contact}/xac-minh', [Admin\ContactController::class, 'verify'])->whereNumber('contact')->name('contacts.verify');
        Route::delete('danh-ba/{contact}', [Admin\ContactController::class, 'destroy'])->whereNumber('contact')->name('contacts.destroy');

        Route::get('huong-dan', [Admin\GuideController::class, 'index'])->name('guides.index');
        Route::get('huong-dan/tao', [Admin\GuideController::class, 'create'])->name('guides.create');
        Route::post('huong-dan/tao', [Admin\GuideController::class, 'store'])->name('guides.store');
        Route::post('huong-dan', [Admin\GuideController::class, 'store'])->name('guides.store.short');
        Route::put('huong-dan/{guide}', [Admin\GuideController::class, 'update'])->name('guides.update.short');
        Route::get('huong-dan/{guide}/sua', [Admin\GuideController::class, 'edit'])->name('guides.edit');
        Route::put('huong-dan/{guide}/sua', [Admin\GuideController::class, 'update'])->name('guides.update');
        Route::post('huong-dan/{guide}/xuat-ban', [Admin\GuideController::class, 'publish'])->name('guides.publish');
        Route::delete('huong-dan/{guide}', [Admin\GuideController::class, 'destroy'])->name('guides.destroy');

        Route::get('vung', [Admin\ZoneController::class, 'index'])->name('zones.index');
        Route::get('vung/{zone}/sua', [Admin\ZoneController::class, 'edit'])->name('zones.edit');
        Route::put('vung/{zone}/sua', [Admin\ZoneController::class, 'update'])->name('zones.update');
        Route::put('vung/{zone}', [Admin\ZoneController::class, 'update'])->name('zones.update.short');

        Route::get('su-co', [Admin\IncidentController::class, 'index'])->name('incidents.index');
        Route::post('su-co/{incident}/duyet', [Admin\IncidentController::class, 'review'])->whereNumber('incident')->name('incidents.review');
        Route::post('su-co/{incident}/tao-canh-bao', [Admin\IncidentController::class, 'createAlert'])->whereNumber('incident')->name('incidents.alert');

        // Chỉ quản trị hệ thống
        Route::middleware('role:admin')->group(function () {
            Route::get('nguon', [Admin\SourceController::class, 'index'])->name('sources.index');
            Route::post('nguon/lay-ngay', [Admin\SourceController::class, 'fetch'])->middleware('throttle:6,1,sources-fetch')->name('sources.fetch');
            Route::get('mo-phong', [Admin\ScenarioController::class, 'index'])->name('scenarios.index');
            Route::post('mo-phong/chay', [Admin\ScenarioController::class, 'run'])->middleware('throttle:10,1,scenarios-run')->name('scenarios.run');
            Route::post('mo-phong/lan-chay/{run}/ket-thuc', [Admin\ScenarioController::class, 'finish'])->whereNumber('run')->name('scenarios.finish');
            Route::post('mo-phong/ket-thuc', [Admin\ScenarioController::class, 'finishBySlug'])->name('scenarios.finish.slug');
            Route::get('nguoi-dung', [Admin\UserController::class, 'index'])->name('users.index');
            Route::patch('nguoi-dung/{user}', [Admin\UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::get('nhat-ky', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            Route::get('sao-luu', [Admin\BackupController::class, 'index'])->name('backup.index');
            Route::post('sao-luu', [Admin\BackupController::class, 'store'])->middleware('throttle:3,1,backup-store')->name('backup.store');
        });
    });
});
