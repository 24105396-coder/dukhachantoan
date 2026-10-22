<?php

use App\Http\Controllers\AboutController;
use App\Http\Controllers\Account\ProfileController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $locale = in_array(session('locale'), ['vi', 'en'], true) ? session('locale') : 'vi';

    return redirect('/'.$locale);
})->name('root');

Route::prefix('{locale}')->where(['locale' => 'vi|en'])->middleware('locale')->group(function () {
    // ---------- Công khai ----------
    Route::get('/', HomeController::class)->name('home');
    Route::get('gioi-thieu', AboutController::class)->name('about');

    // ---------- Xác thực ----------
    Route::middleware('guest')->group(function () {
        Route::get('dang-nhap', [LoginController::class, 'create'])->name('login');
        Route::post('dang-nhap', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
        Route::get('dang-ky', [RegisterController::class, 'create'])->name('register');
        Route::post('dang-ky', [RegisterController::class, 'store'])->middleware('throttle:6,1')->name('register.store');
        Route::get('quen-mat-khau', [PasswordResetController::class, 'create'])->name('password.request');
        Route::post('quen-mat-khau', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
        Route::get('dat-lai-mat-khau/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('dat-lai-mat-khau', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    });
    Route::post('dang-xuat', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

    // ---------- Tài khoản (mọi vai trò đã đăng nhập) ----------
    Route::middleware('auth')->group(function () {
        Route::get('tai-khoan', [ProfileController::class, 'edit'])->name('account.edit');
        Route::put('tai-khoan', [ProfileController::class, 'update'])->name('account.update');
        Route::patch('tai-khoan/giao-dien', [ProfileController::class, 'theme'])->name('account.theme');
        Route::patch('tai-khoan/mat-khau', [ProfileController::class, 'password'])->name('account.password.update');
        // Liên kết cũ (menu tài khoản bản kế thừa) → trang tài khoản mới
        Route::get('tai-khoan/ho-so', fn () => redirect(localized_route('account.edit')))->name('account.profile');
    });

    // ---------- Quản trị: cơ quan hỗ trợ + quản trị hệ thống ----------
    // auth trước role: khách được chuyển tới đăng nhập (giữ url.intended), sai vai trò → 403
    Route::prefix('quan-tri')->name('admin.')->middleware(['auth', 'role:agency,admin'])->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        Route::middleware('role:admin')->group(function () {
            Route::get('nguoi-dung', [Admin\UserController::class, 'index'])->name('users.index');
            Route::patch('nguoi-dung/{user}', [Admin\UserController::class, 'update'])->whereNumber('user')->name('users.update');
            Route::get('nhat-ky', [Admin\AuditLogController::class, 'index'])->name('audit.index');
            Route::get('sao-luu', [Admin\BackupController::class, 'index'])->name('backup.index');
            Route::post('sao-luu', [Admin\BackupController::class, 'store'])->middleware('throttle:3,1')->name('backup.store');
        });
    });
});
