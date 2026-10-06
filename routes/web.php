<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Internal\AuditLogController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\Inventory\AssetController;
use App\Http\Controllers\Internal\Inventory\AssetStatusController;
use App\Http\Controllers\Internal\Inventory\CategoryController;
use App\Http\Controllers\Internal\Inventory\EquipmentController;
use App\Http\Controllers\Internal\Inventory\LabelController;
use App\Http\Controllers\Internal\Inventory\LocationController;
use App\Http\Controllers\Internal\Inventory\MovementController;
use App\Http\Controllers\Internal\Inventory\ScanController;
use App\Http\Controllers\Internal\Inventory\StockController;
use App\Http\Controllers\Internal\LookupController;
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
    Route::get('/settings/options/{group?}', [LookupController::class, 'index'])->middleware('can:settings.view')->name('settings.options');
    Route::post('/settings/options/{group}', [LookupController::class, 'store'])->middleware('can:settings.manage')->name('settings.options.store');
    Route::put('/settings/options/item/{lookup}', [LookupController::class, 'update'])->middleware('can:settings.manage')->name('settings.options.update');

    /*
    | Inventory. Record-level rules are in EquipmentPolicy / EquipmentAssetPolicy
    | and the inventory services; setup screens need inventory.configure.
    */
    Route::get('/scan/{token}', ScanController::class)->where('token', '[0-9A-Za-z]{26}')->name('scan');

    Route::prefix('inventory')->name('inventory.')->group(function () {
        Route::get('/equipment', [EquipmentController::class, 'index'])->name('equipment.index');
        Route::get('/equipment/create', [EquipmentController::class, 'create'])->name('equipment.create');
        Route::post('/equipment', [EquipmentController::class, 'store'])->name('equipment.store');
        Route::get('/equipment/{equipment}', [EquipmentController::class, 'show'])->withTrashed()->name('equipment.show');
        Route::get('/equipment/{equipment}/image', [EquipmentController::class, 'image'])->withTrashed()->name('equipment.image');
        Route::get('/equipment/{equipment}/edit', [EquipmentController::class, 'edit'])->name('equipment.edit');
        Route::put('/equipment/{equipment}', [EquipmentController::class, 'update'])->name('equipment.update');
        Route::delete('/equipment/{equipment}', [EquipmentController::class, 'destroy'])->name('equipment.destroy');
        Route::post('/equipment/{id}/restore', [EquipmentController::class, 'restore'])->whereNumber('id')->name('equipment.restore');
        Route::post('/equipment/{equipment}/stock', StockController::class)->name('equipment.stock');
        Route::get('/equipment/{equipment}/assets/create', [AssetController::class, 'create'])->name('assets.create');
        Route::post('/equipment/{equipment}/assets', [AssetController::class, 'store'])->name('assets.store');

        Route::get('/assets', [AssetController::class, 'index'])->name('assets.index');
        Route::get('/assets/{asset}', [AssetController::class, 'show'])->withTrashed()->name('assets.show');
        Route::get('/assets/{asset}/edit', [AssetController::class, 'edit'])->name('assets.edit');
        Route::put('/assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::post('/assets/{asset}/status', [AssetController::class, 'status'])->name('assets.status');
        Route::post('/assets/{asset}/move', [AssetController::class, 'move'])->name('assets.move');
        Route::post('/assets/{asset}/condition', [AssetController::class, 'condition'])->name('assets.condition');
        Route::delete('/assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');
        Route::post('/assets/{id}/restore', [AssetController::class, 'restore'])->whereNumber('id')->name('assets.restore');

        Route::get('/movements', MovementController::class)->name('movements');
        Route::get('/labels', LabelController::class)->name('labels');

        Route::middleware('can:inventory.configure')->prefix('setup')->name('setup.')->group(function () {
            Route::get('/categories', [CategoryController::class, 'index'])->name('categories');
            Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::put('/categories/{category}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])->name('categories.destroy');
            Route::post('/categories/{id}/restore', [CategoryController::class, 'restore'])->whereNumber('id')->name('categories.restore');

            Route::get('/locations', [LocationController::class, 'index'])->name('locations');
            Route::post('/locations', [LocationController::class, 'store'])->name('locations.store');
            Route::put('/locations/{location}', [LocationController::class, 'update'])->name('locations.update');
            Route::delete('/locations/{location}', [LocationController::class, 'destroy'])->name('locations.destroy');
            Route::post('/locations/{id}/restore', [LocationController::class, 'restore'])->whereNumber('id')->name('locations.restore');

            Route::get('/statuses', [AssetStatusController::class, 'index'])->name('statuses');
            Route::post('/statuses', [AssetStatusController::class, 'store'])->name('statuses.store');
            Route::put('/statuses/{status}', [AssetStatusController::class, 'update'])->name('statuses.update');
        });
    });
});
