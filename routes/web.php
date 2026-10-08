<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Internal\Allocation\AvailabilityController;
use App\Http\Controllers\Internal\Allocation\EventEquipmentController;
use App\Http\Controllers\Internal\Allocation\LoadListController;
use App\Http\Controllers\Internal\Allocation\ReturnController;
use App\Http\Controllers\Internal\AuditLogController;
use App\Http\Controllers\Internal\Commercial\CustomerController;
use App\Http\Controllers\Internal\Commercial\PackageController;
use App\Http\Controllers\Internal\Commercial\QuotationController;
use App\Http\Controllers\Internal\DashboardController;
use App\Http\Controllers\Internal\DocumentController;
use App\Http\Controllers\Internal\Events\CalendarController;
use App\Http\Controllers\Internal\Events\EventController;
use App\Http\Controllers\Internal\Events\StaffController;
use App\Http\Controllers\Internal\Events\TeamController;
use App\Http\Controllers\Internal\Inventory\AssetController;
use App\Http\Controllers\Internal\Inventory\AssetStatusController;
use App\Http\Controllers\Internal\Inventory\CategoryController;
use App\Http\Controllers\Internal\Inventory\EquipmentController;
use App\Http\Controllers\Internal\Inventory\LabelController;
use App\Http\Controllers\Internal\Inventory\LocationController;
use App\Http\Controllers\Internal\Inventory\MovementController;
use App\Http\Controllers\Internal\Inventory\ScanController;
use App\Http\Controllers\Internal\Inventory\StockController;
use App\Http\Controllers\Internal\Logistics\TripController;
use App\Http\Controllers\Internal\Logistics\VehicleController;
use App\Http\Controllers\Internal\LookupController;
use App\Http\Controllers\Internal\Maintenance\MaintenanceController;
use App\Http\Controllers\Internal\Maintenance\ScheduleController;
use App\Http\Controllers\Internal\NotificationController;
use App\Http\Controllers\Internal\ProfileController;
use App\Http\Controllers\Internal\ReportController;
use App\Http\Controllers\Internal\RequestController as InternalRequestController;
use App\Http\Controllers\Internal\RoleController;
use App\Http\Controllers\Internal\SearchController;
use App\Http\Controllers\Internal\ServiceController;
use App\Http\Controllers\Internal\SettingsController;
use App\Http\Controllers\Internal\SystemController;
use App\Http\Controllers\Internal\UserController;
use App\Http\Controllers\Public\HomeController;
use App\Http\Controllers\Public\QuotationController as PublicQuotationController;
use App\Http\Controllers\Public\RequestController;
use App\Http\Controllers\Public\TrackingController;
use App\Http\Controllers\Setup\InstallController;
use Illuminate\Support\Facades\Route;

/*
| Public portal. Never loads internal (inventory, staff, pricing) data.
*/
Route::get('/', HomeController::class)->name('home');
Route::view('/offline', 'public.offline')->name('offline');

Route::get('/request', [RequestController::class, 'create'])->name('requests.create');
Route::post('/request', [RequestController::class, 'store'])->middleware('throttle:public-request')->name('requests.store');
Route::get('/request/received/{token}', [RequestController::class, 'received'])->name('requests.received');
Route::get('/track', [TrackingController::class, 'form'])->name('requests.track-form');
Route::post('/track', [TrackingController::class, 'lookup'])->middleware('throttle:10,1')->name('requests.track-lookup');
Route::get('/track/{token}', [TrackingController::class, 'show'])->middleware('throttle:60,1')->name('requests.track');
Route::get('/q/{token}', [PublicQuotationController::class, 'show'])->middleware('throttle:60,1')->name('quotations.public');
Route::post('/q/{token}', [PublicQuotationController::class, 'respond'])->middleware('throttle:10,1')->name('quotations.public.respond');

/*
| Web installer (D69): 404 unless NEBO_INSTALLER is on and the install isn't finished.
*/
Route::get('/install', [InstallController::class, 'show'])->name('install.show');
Route::post('/install', [InstallController::class, 'store'])->middleware('throttle:10,1')->name('install.store');
Route::get('/install/run', [InstallController::class, 'run'])->name('install.run');

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

    Route::middleware('can:system.manage')->group(function () {
        Route::get('/settings/system', [SystemController::class, 'show'])->name('settings.system');
        Route::post('/settings/system/update', [SystemController::class, 'update'])->name('settings.system.update');
        Route::delete('/settings/system/sample-data', [SystemController::class, 'clearSample'])->name('settings.system.sample.clear');
    });
    Route::get('/settings', [SettingsController::class, 'edit'])->middleware('can:settings.view')->name('settings.edit');
    Route::put('/settings', [SettingsController::class, 'update'])->middleware('can:settings.manage')->name('settings.update');
    Route::middleware('can:services.manage')->group(function () {
        Route::get('/settings/services', [ServiceController::class, 'index'])->name('settings.services');
        Route::post('/settings/services', [ServiceController::class, 'store'])->name('settings.services.store');
        Route::put('/settings/services/{service}', [ServiceController::class, 'update'])->name('settings.services.update');
    });

    Route::get('/requests', [InternalRequestController::class, 'index'])->name('requests.index');
    Route::get('/requests/{request}', [InternalRequestController::class, 'show'])->name('requests.show');
    Route::post('/requests/{request}/status', [InternalRequestController::class, 'status'])->name('requests.status');
    Route::post('/requests/{request}/assign', [InternalRequestController::class, 'assign'])->name('requests.assign');
    Route::post('/requests/{request}/notes', [InternalRequestController::class, 'note'])->name('requests.notes');

    Route::get('/requests/{request}/event', [EventController::class, 'fromRequest'])->name('requests.event.create');
    Route::post('/requests/{request}/event', [EventController::class, 'convert'])->name('requests.event.store');

    Route::get('/calendar', CalendarController::class)->name('calendar');
    Route::get('/events', [EventController::class, 'index'])->name('events.index');
    Route::get('/events/create', [EventController::class, 'create'])->name('events.create');
    Route::post('/events', [EventController::class, 'store'])->name('events.store');
    Route::get('/events/{event}', [EventController::class, 'show'])->withTrashed()->name('events.show');
    Route::get('/events/{event}/edit', [EventController::class, 'edit'])->name('events.edit');
    Route::put('/events/{event}', [EventController::class, 'update'])->name('events.update');
    Route::post('/events/{event}/status', [EventController::class, 'status'])->name('events.status');
    Route::post('/events/{event}/notes', [EventController::class, 'note'])->name('events.notes');
    Route::delete('/events/{event}', [EventController::class, 'destroy'])->name('events.destroy');
    Route::post('/events/{id}/restore', [EventController::class, 'restore'])->whereNumber('id')->name('events.restore');
    Route::post('/events/{event}/team', [TeamController::class, 'store'])->name('events.team.store');
    Route::delete('/events/{event}/team/{member}', [TeamController::class, 'destroy'])->name('events.team.destroy');

    Route::post('/events/{event}/requirements', [EventEquipmentController::class, 'setRequirement'])->name('events.requirements.store');
    Route::delete('/events/{event}/requirements/{requirement}', [EventEquipmentController::class, 'removeRequirement'])->name('events.requirements.destroy');
    Route::post('/events/{event}/allocations', [EventEquipmentController::class, 'allocate'])->name('events.allocations.store');
    Route::delete('/events/{event}/allocations/{allocation}', [EventEquipmentController::class, 'release'])->name('events.allocations.destroy');
    Route::get('/events/{event}/load-list', [LoadListController::class, 'show'])->name('events.load-list');
    Route::get('/events/{event}/load-list/print', [LoadListController::class, 'print'])->name('events.load-list.print');
    Route::post('/events/{event}/load-list', [LoadListController::class, 'sync'])->name('events.load-list.sync');
    Route::post('/events/{event}/load-list/items/{item}', [LoadListController::class, 'item'])->name('events.load-list.item');
    Route::post('/events/{event}/load-list/advance', [LoadListController::class, 'advance'])->name('events.load-list.advance');
    Route::post('/events/{event}/load-list/dispatch', [LoadListController::class, 'dispatch'])->name('events.load-list.dispatch');
    Route::get('/events/{event}/returns', [ReturnController::class, 'show'])->name('events.returns');
    Route::post('/events/{event}/returns', [ReturnController::class, 'store'])->name('events.returns.store');
    Route::get('/load-lists', [LoadListController::class, 'index'])->name('load-lists.index');
    Route::get('/availability', AvailabilityController::class)->name('availability');

    Route::prefix('logistics')->name('logistics.')->group(function () {
        Route::get('/', [TripController::class, 'index'])->name('index');
        Route::get('/trips/create', [TripController::class, 'create'])->name('trips.create');
        Route::post('/trips', [TripController::class, 'store'])->name('trips.store');
        Route::get('/trips/{trip}', [TripController::class, 'show'])->name('trips.show');
        Route::get('/trips/{trip}/edit', [TripController::class, 'edit'])->name('trips.edit');
        Route::put('/trips/{trip}', [TripController::class, 'update'])->name('trips.update');
        Route::post('/trips/{trip}/status', [TripController::class, 'transition'])->name('trips.status');
        Route::post('/trips/{trip}/notes', [TripController::class, 'note'])->name('trips.notes');
        Route::get('/vehicles', [VehicleController::class, 'index'])->name('vehicles.index');
        Route::get('/vehicles/create', [VehicleController::class, 'create'])->name('vehicles.create');
        Route::post('/vehicles', [VehicleController::class, 'store'])->name('vehicles.store');
        Route::get('/vehicles/{vehicle}', [VehicleController::class, 'show'])->withTrashed()->name('vehicles.show');
        Route::get('/vehicles/{vehicle}/edit', [VehicleController::class, 'edit'])->name('vehicles.edit');
        Route::put('/vehicles/{vehicle}', [VehicleController::class, 'update'])->name('vehicles.update');
        Route::delete('/vehicles/{vehicle}', [VehicleController::class, 'destroy'])->name('vehicles.destroy');
        Route::post('/vehicles/{id}/restore', [VehicleController::class, 'restore'])->whereNumber('id')->name('vehicles.restore');
        Route::post('/vehicles/{vehicle}/notes', [VehicleController::class, 'note'])->name('vehicles.notes');
    });

    Route::get('/reports', [ReportController::class, 'index'])->name('reports.index');
    Route::get('/reports/{report}', [ReportController::class, 'show'])->where('report', '[a-z]+')->name('reports.show');

    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/create', [CustomerController::class, 'create'])->name('customers.create');
    Route::post('/customers', [CustomerController::class, 'store'])->name('customers.store');
    Route::get('/customers/{customer}', [CustomerController::class, 'show'])->name('customers.show');
    Route::get('/customers/{customer}/edit', [CustomerController::class, 'edit'])->name('customers.edit');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->name('customers.update');
    Route::post('/customers/{customer}/reviewed', [CustomerController::class, 'reviewed'])->name('customers.reviewed');
    Route::post('/customers/{customer}/merge', [CustomerController::class, 'merge'])->name('customers.merge');
    Route::post('/customers/{customer}/notes', [CustomerController::class, 'note'])->name('customers.notes');

    Route::get('/quotations', [QuotationController::class, 'index'])->name('quotations.index');
    Route::get('/quotations/create', [QuotationController::class, 'create'])->name('quotations.create');
    Route::post('/quotations', [QuotationController::class, 'store'])->name('quotations.store');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->name('quotations.show');
    Route::get('/quotations/{quotation}/edit', [QuotationController::class, 'edit'])->name('quotations.edit');
    Route::put('/quotations/{quotation}', [QuotationController::class, 'update'])->name('quotations.update');
    Route::get('/quotations/{quotation}/print', [QuotationController::class, 'print'])->name('quotations.print');
    Route::post('/quotations/{quotation}/package', [QuotationController::class, 'addPackage'])->name('quotations.package');
    Route::post('/quotations/{quotation}/send', [QuotationController::class, 'send'])->name('quotations.send');
    Route::post('/quotations/{quotation}/respond', [QuotationController::class, 'respond'])->name('quotations.respond');
    Route::post('/quotations/{quotation}/revise', [QuotationController::class, 'revise'])->name('quotations.revise');
    Route::post('/quotations/{quotation}/cancel', [QuotationController::class, 'cancel'])->name('quotations.cancel');
    Route::post('/quotations/{quotation}/duplicate', [QuotationController::class, 'duplicate'])->name('quotations.duplicate');
    Route::post('/quotations/{quotation}/notes', [QuotationController::class, 'note'])->name('quotations.notes');

    Route::get('/packages', [PackageController::class, 'index'])->name('packages.index');
    Route::get('/packages/create', [PackageController::class, 'create'])->name('packages.create');
    Route::post('/packages', [PackageController::class, 'store'])->name('packages.store');
    Route::get('/packages/{package}/edit', [PackageController::class, 'edit'])->name('packages.edit');
    Route::put('/packages/{package}', [PackageController::class, 'update'])->name('packages.update');
    Route::delete('/packages/{package}', [PackageController::class, 'destroy'])->name('packages.destroy');

    Route::get('/maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('/maintenance/create', [MaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('/maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::post('/maintenance/schedules', [ScheduleController::class, 'store'])->name('maintenance.schedules.store');
    Route::put('/maintenance/schedules/{schedule}', [ScheduleController::class, 'update'])->name('maintenance.schedules.update');
    Route::post('/maintenance/schedules/{schedule}/job', [ScheduleController::class, 'openJob'])->name('maintenance.schedules.job');
    Route::get('/maintenance/{record}', [MaintenanceController::class, 'show'])->name('maintenance.show');
    Route::post('/maintenance/{record}/schedule', [MaintenanceController::class, 'schedule'])->name('maintenance.schedule');
    Route::post('/maintenance/{record}/start', [MaintenanceController::class, 'start'])->name('maintenance.start');
    Route::post('/maintenance/{record}/complete', [MaintenanceController::class, 'complete'])->name('maintenance.complete');
    Route::post('/maintenance/{record}/cancel', [MaintenanceController::class, 'cancel'])->name('maintenance.cancel');
    Route::post('/maintenance/{record}/notes', [MaintenanceController::class, 'note'])->name('maintenance.notes');

    Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
    Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
    Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
    Route::get('/staff/{staff}', [StaffController::class, 'show'])->name('staff.show');
    Route::get('/staff/{staff}/edit', [StaffController::class, 'edit'])->name('staff.edit');
    Route::put('/staff/{staff}', [StaffController::class, 'update'])->name('staff.update');

    Route::get('/documents/{document}', [DocumentController::class, 'download'])->name('documents.download');
    Route::delete('/documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    Route::post('/documents/{type}/{id}', [DocumentController::class, 'store'])->whereNumber('id')->name('documents.store');

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
