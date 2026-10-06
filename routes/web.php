<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Internal\AuditLogController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\NotificationController;
use App\Http\Controllers\Internal\ProfileController;
use App\Http\Controllers\Internal\RoleController;
use App\Http\Controllers\Internal\SearchController;
use App\Http\Controllers\Internal\SettingsController;
use App\Http\Controllers\Internal\UserController;
use App\Http\Controllers\Public\HomeController;
use Illuminate\Support\Facades\Route;

/*
| Public portal. Never loads internal (inventory, staff, pricing) data.
*/
Route::get('/', HomeController::class)->name('home');
Route::view('/offline', 'public.offline')->name('offline');

/*
| Authentication
*/
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:login')->name('login.store');
    Route::get('/forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'email'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:5,1')->name('password.update');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');

/*
| Internal operations system
*/
Route::prefix('app')->name('app.')->middleware(['auth', 'auth.session', 'active'])->group(function () {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('/search', SearchController::class)->middleware('throttle:search')->name('search');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'password'])->middleware('throttle:6,1')->name('profile.password');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');
    Route::get('/notifications/{notification}', [NotificationController::class, 'open'])->name('notifications.open');

    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/create', [UserController::class, 'create'])->name('users.create');
    Route::post('/users', [UserController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
    Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::put('/users/{user}/status', [UserController::class, 'status'])->name('users.status');
    Route::put('/users/{user}/password', [UserController::class, 'password'])->name('users.password');

    Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    Route::get('/roles/create', [RoleController::class, 'create'])->name('roles.create');
    Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    Route::get('/roles/{role}/edit', [RoleController::class, 'edit'])->name('roles.edit');
    Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');

    Route::middleware('can:audit.view')->group(function () {
        Route::get('/audit', [AuditLogController::class, 'index'])->name('audit.index');
        Route::get('/audit/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
    });

    Route::get('/settings', [SettingsController::class, 'edit'])->middleware('can:settings.view')->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');
});
