<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\PayableReportController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'shipments.index' : 'login');
});

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ===== Follow Up Shipment =====
    Route::middleware('permission:shipments.view')->group(function () {
        Route::get('/shipments',             [ShipmentController::class, 'redirectToCurrent'])->name('shipments.index');
        Route::get('/shipments/{period}',    [ShipmentController::class, 'show'])->name('shipments.show')->where('period', '\d{4}-\d{2}');
        Route::get('/shipments/{period}/data', [ShipmentController::class, 'data'])->name('shipments.data')->where('period', '\d{4}-\d{2}');
        Route::put('/me/shipment-column-prefs', [ShipmentController::class, 'updateColumnPrefs'])->name('shipments.columnPrefs');
    });
    Route::middleware('permission:shipments.create')->group(function () {
        Route::post('/shipments/months',                [ShipmentController::class, 'createPeriod'])->name('shipments.createPeriod');
        Route::post('/shipments/{period}/{direction}',  [ShipmentController::class, 'store'])->name('shipments.store')->where(['period' => '\d{4}-\d{2}', 'direction' => 'import|export']);
    });
    Route::middleware('permission:shipments.update')->group(function () {
        Route::post('/shipments/{period}/bulk',           [ShipmentController::class, 'bulk'])->name('shipments.bulk')->where('period', '\d{4}-\d{2}');
        Route::post('/shipments/{period}/reset-snapshot', [ShipmentController::class, 'resetSnapshot'])->name('shipments.resetSnapshot')->where('period', '\d{4}-\d{2}');
        Route::put ('/shipments/row/{shipment}',          [ShipmentController::class, 'update'])->name('shipments.update');
    });
    Route::middleware('permission:shipments.delete')->group(function () {
        Route::delete('/shipments/row/{shipment}', [ShipmentController::class, 'destroy'])->name('shipments.destroy');
    });

    // ===== Reports - Payable =====
    Route::prefix('reports/payable')->name('reports.payable.')->group(function () {
        Route::middleware('permission:reports.view')->group(function () {
            Route::get('/',                  [PayableReportController::class, 'index'])->name('index');
            Route::get('/initial',           [PayableReportController::class, 'initialIndex'])->name('initial.index');
            Route::get('/{report}',          [PayableReportController::class, 'show'])->name('show');
        });
        Route::middleware('permission:reports.create')->group(function () {
            Route::post('/',         [PayableReportController::class, 'store'])->name('store');
            Route::post('/initial',  [PayableReportController::class, 'initialStore'])->name('initial.store');
        });
        Route::middleware('permission:reports.delete')->group(function () {
            Route::delete('/{report}',          [PayableReportController::class, 'destroy'])->name('destroy');
            Route::delete('/initial/{balance}', [PayableReportController::class, 'initialDestroy'])->name('initial.destroy');
        });
    });

    // ===== Users =====
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
    });
    Route::middleware('permission:users.create')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:users.update')->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/column-permissions', [UserController::class, 'updateColumnPermissions'])->name('users.columnPermissions');
    });
    Route::middleware('permission:users.delete')->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // ===== Roles =====
    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles', [RoleController::class, 'index'])->name('roles.index');
    });
    Route::middleware('permission:roles.create')->group(function () {
        Route::post('/roles', [RoleController::class, 'store'])->name('roles.store');
    });
    Route::middleware('permission:roles.update')->group(function () {
        Route::put('/roles/{role}', [RoleController::class, 'update'])->name('roles.update');
    });
    Route::middleware('permission:roles.delete')->group(function () {
        Route::delete('/roles/{role}', [RoleController::class, 'destroy'])->name('roles.destroy');
    });
});
