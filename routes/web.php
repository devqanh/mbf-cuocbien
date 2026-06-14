<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\TwoFactorChallengeController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SystemSettingController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TruckingController;
use App\Http\Controllers\TruckingV2Controller;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'trucking2.shipments' : 'login');
});

// ===== Yêu cầu chi (mobile SPA, có đăng nhập) — tài xế gửi đề nghị chi, kế toán duyệt sau =====
Route::get ('/yeu-cau-chi', [TruckingV2Controller::class, 'spendRequestPage'])->name('trucking2.spendRequest');
Route::post('/yeu-cau-chi/login',  [TruckingV2Controller::class, 'spendLogin'])->name('trucking2.spendRequest.login');
Route::post('/yeu-cau-chi/logout', [TruckingV2Controller::class, 'spendLogout'])->name('trucking2.spendRequest.logout');
Route::get ('/yeu-cau-chi/history', [TruckingV2Controller::class, 'spendHistory'])->name('trucking2.spendRequest.history');
Route::post('/yeu-cau-chi/{cost}/cancel', [TruckingV2Controller::class, 'cancelMySpendRequest'])->name('trucking2.spendRequest.cancel');
Route::post('/yeu-cau-chi/{cost}/update', [TruckingV2Controller::class, 'updateMySpendRequest'])->name('trucking2.spendRequest.update');
Route::post('/yeu-cau-chi', [TruckingV2Controller::class, 'submitSpendRequest'])->name('trucking2.spendRequest.submit');

// ===== Link kế hoạch CÔNG KHAI (lái xe, không đăng nhập) — token bí mật trong URL =====
Route::get ('/ke-hoach/{token}',                 [TruckingV2Controller::class, 'planPublicPage'])->name('trucking2.plan.public');
Route::get ('/ke-hoach/{token}/data',            [TruckingV2Controller::class, 'planPublicData'])->name('trucking2.plan.public.data');
Route::post('/ke-hoach/{token}/{ship}/update',   [TruckingV2Controller::class, 'planPublicUpdate'])->name('trucking2.plan.public.update');
Route::delete('/ke-hoach/{token}/{ship}/photo/{att}', [TruckingV2Controller::class, 'planPublicDeletePhoto'])->name('trucking2.plan.public.photo.delete')->whereNumber('att');

// ===== Tài liệu Trucking — CÔNG KHAI (không cần đăng nhập) để gửi kế toán =====
Route::get('/tailieu',          [TruckingController::class, 'docs'])->name('trucking.docs');
Route::get('/tailieu/download', [TruckingController::class, 'docsDownload'])->name('trucking.docsDownload');
Route::post('/tailieu/notes',   [TruckingController::class, 'saveNotes'])->name('trucking.saveNotes');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

    // Bước 2 khi user bật 2FA (chưa đăng nhập — thông tin tạm giữ trong session)
    Route::get ('/login/2fa', [TwoFactorChallengeController::class, 'show'])->name('login.2fa');
    Route::post('/login/2fa', [TwoFactorChallengeController::class, 'store'])->name('login.2fa.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ===== Profile (mọi user đã login) =====
    Route::get('/profile',           [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/info',      [ProfileController::class, 'updateInfo'])->name('profile.info');
    Route::put('/profile/password',  [ProfileController::class, 'updatePassword'])->name('profile.password');
    // Quản lý thiết bị / phiên đăng nhập
    Route::post  ('/profile/sessions/logout-others', [ProfileController::class, 'revokeOtherSessions'])->name('profile.sessions.logoutOthers');
    Route::delete('/profile/sessions/{sessionId}',   [ProfileController::class, 'revokeSession'])->name('profile.sessions.revoke');

    // Xác thực 2 lớp (2FA)
    Route::post  ('/profile/2fa/start',           [ProfileController::class, 'startTwoFactor'])->name('profile.2fa.start');
    Route::post  ('/profile/2fa/confirm',         [ProfileController::class, 'confirmTwoFactor'])->name('profile.2fa.confirm');
    Route::post  ('/profile/2fa/recovery-codes',  [ProfileController::class, 'regenerateRecoveryCodes'])->name('profile.2fa.recovery');
    Route::delete('/profile/2fa',                 [ProfileController::class, 'disableTwoFactor'])->name('profile.2fa.disable');

    // ===== Follow Up Shipment ===== (TẠM TẮT qua config features.shipments)
    if (config('features.shipments')) {
    Route::middleware('permission:shipments.view')->group(function () {
        Route::get('/shipments',             [ShipmentController::class, 'redirectToCurrent'])->name('shipments.index');
        Route::get('/shipments/{period}',    [ShipmentController::class, 'show'])->name('shipments.show')->where('period', '\d{4}-\d{2}');
        Route::get('/shipments/{period}/data', [ShipmentController::class, 'data'])->name('shipments.data')->where('period', '\d{4}-\d{2}');
        Route::get('/shipments/{period}/export', [ShipmentController::class, 'export'])->name('shipments.export')->where('period', '\d{4}-\d{2}');
        Route::put('/me/shipment-column-prefs', [ShipmentController::class, 'updateColumnPrefs'])->name('shipments.columnPrefs');

        // Debug endpoint — dump current snapshot for inspection
        Route::get('/shipments/{period}/debug-snapshot', function (string $period) {
            $svc = app(\App\Services\SheetSnapshotService::class);
            $snap = $svc->get('shipments_grid_' . $period);
            return response()->json([
                'exists'   => $snap !== null,
                'version'  => $snap?->version,
                'updated_at' => $snap?->updated_at,
                'payload_type' => $snap ? gettype($snap->payload) : null,
                'payload_keys' => $snap && is_array($snap->payload) ? array_keys($snap->payload) : null,
                'has_formatting' => $snap && is_array($snap->payload) && isset($snap->payload['formatting']),
                'formatting_import_count' => $snap && is_array($snap->payload)
                    && isset($snap->payload['formatting']['import'])
                    ? count($snap->payload['formatting']['import']) : null,
                'formatting_export_count' => $snap && is_array($snap->payload)
                    && isset($snap->payload['formatting']['export'])
                    ? count($snap->payload['formatting']['export']) : null,
                'first_import_entry' => $snap && is_array($snap->payload)
                    && ! empty($snap->payload['formatting']['import'])
                    ? $snap->payload['formatting']['import'][0] : null,
            ]);
        })->where('period', '\d{4}-\d{2}');
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
    } // end if features.shipments

    // ===== Trucking cũ (Luckysheet) ĐÃ GỠ — giữ tên 'trucking.index' làm alias chuyển sang v2
    //        (nhiều nơi vẫn link 'Trang chủ' tới route này) =====
    Route::get('/trucking', fn () => redirect()->route('trucking2.shipments'))->name('trucking.index');

    // ===== Trucking v2 (record + popup) — phân quyền TÁCH theo 4 tính năng =====
    Route::prefix('trucking-v2')->name('trucking2.')->group(function () {
        // --- Lô hàng ---
        Route::middleware('permission:shipments.view')->group(function () {
            Route::get('/', fn () => redirect()->route('trucking2.shipments'));
            Route::get('/lo-hang',        [TruckingV2Controller::class, 'shipments'])->name('shipments');
            Route::get('/shipments-page', [TruckingV2Controller::class, 'shipmentsPage'])->name('shipmentsPage');
            Route::get('/config',         [TruckingV2Controller::class, 'configData'])->name('configData');
            Route::get('/bootstrap',      [TruckingV2Controller::class, 'bootstrap'])->name('bootstrap');
            Route::get('/phi-xe',                  [TruckingV2Controller::class, 'tripCostPage'])->name('tripCost');
            Route::get('/phi-xe/tao',              [TruckingV2Controller::class, 'createTripCost'])->name('tripCost.create');
            Route::get('/phi-xe/compute',          [TruckingV2Controller::class, 'tripCostCompute'])->name('tripCost.compute');
            Route::get('/phi-xe/{tripCost}',       [TruckingV2Controller::class, 'viewTripCost'])->name('tripCost.view');
            Route::get('/phi-xe/{tripCost}/context', [TruckingV2Controller::class, 'tripCostContext'])->name('tripCost.context');
            Route::get('/ke-hoach',                [TruckingV2Controller::class, 'planLinks'])->name('plan');   // quản lý link kế hoạch
        });
        Route::middleware('permission:shipments.create')->group(function () {
            Route::post('/trip-costs', [TruckingV2Controller::class, 'storeTripCost'])->name('tripCost.store');
        });
        Route::middleware('permission:shipments.update')->group(function () {
            Route::put('/trip-costs/{tripCost}', [TruckingV2Controller::class, 'updateTripCost'])->name('tripCost.update');
            Route::post('/plan-links',                       [TruckingV2Controller::class, 'createPlanLink'])->name('plan.create');
            Route::put('/plan-links/{planLink}/toggle',      [TruckingV2Controller::class, 'togglePlanLink'])->name('plan.toggle')->whereNumber('planLink');
            Route::delete('/plan-links/{planLink}',          [TruckingV2Controller::class, 'destroyPlanLink'])->name('plan.destroy')->whereNumber('planLink');
        });
        Route::middleware('permission:shipments.delete')->group(function () {
            Route::delete('/trip-costs/{tripCost}', [TruckingV2Controller::class, 'destroyTripCost'])->name('tripCost.destroy');
        });
        Route::middleware('permission:shipments.create')->group(function () {
            Route::post('/shipments',             [TruckingV2Controller::class, 'storeShipment'])->name('shipments.store');
            Route::post('/shipment-import/check', [TruckingV2Controller::class, 'checkShipments'])->name('shipmentCheck');
            Route::post('/shipment-import',       [TruckingV2Controller::class, 'importShipments'])->name('shipmentImport');
        });
        Route::middleware('permission:shipments.update')->group(function () {
            Route::put('/shipments/{shipment}', [TruckingV2Controller::class, 'updateShipment'])->name('shipments.update');
        });
        Route::middleware('permission:shipments.delete')->group(function () {
            Route::delete('/shipments/{shipment}', [TruckingV2Controller::class, 'destroyShipment'])->name('shipments.destroy');
        });

        // --- Bảng giá ---
        Route::middleware('permission:prices.view')->group(function () {
            Route::get('/bang-gia',        [TruckingV2Controller::class, 'prices'])->name('prices');
            Route::get('/customer-prices', [TruckingV2Controller::class, 'customerPrices'])->name('customerPrices');
        });
        Route::middleware('permission:prices.update')->group(function () {
            Route::post('/price-import', [TruckingV2Controller::class, 'importPrices'])->name('priceImport');
        });

        // --- Bảng kê ---
        Route::middleware('permission:statements.view')->group(function () {
            Route::get('/bang-ke',                     [TruckingV2Controller::class, 'statements'])->name('statements');
            Route::get('/bang-ke/tao',                 [TruckingV2Controller::class, 'createStatement'])->name('statements.create');
            Route::get('/bang-ke/{statement}',         [TruckingV2Controller::class, 'viewStatement'])->name('statements.view');
            Route::get('/bang-ke/{statement}/context', [TruckingV2Controller::class, 'statementContext'])->name('statements.context');
            Route::get('/bang-ke/{statement}/excel',   [TruckingV2Controller::class, 'exportStatement'])->name('statements.excel');
        });
        Route::middleware('permission:statements.create')->group(function () {
            Route::post('/statements', [TruckingV2Controller::class, 'storeStatement'])->name('statements.store');
        });
        Route::middleware('permission:statements.update')->group(function () {
            Route::put('/statements/{statement}', [TruckingV2Controller::class, 'updateStatement'])->name('statements.update');
        });
        Route::middleware('permission:statements.delete')->group(function () {
            Route::delete('/statements/{statement}', [TruckingV2Controller::class, 'destroyStatement'])->name('statements.destroy');
        });

        // --- Cài đặt Trucking (danh mục, khách hàng, đội xe, cấu hình) ---
        Route::middleware('permission:settings.view')->group(function () {
            Route::get('/cai-dat',        [TruckingV2Controller::class, 'settings'])->name('settings');
            Route::get('/catalog/{type}', [TruckingV2Controller::class, 'catalogData'])->name('catalogData');   // lazy-load 1 tab
            // Quản lý xe (xe MBF nội bộ)
            Route::get('/quan-ly-xe',                 [TruckingV2Controller::class, 'fleet'])->name('fleet');
            Route::get('/quan-ly-tai-san-list',       [TruckingV2Controller::class, 'assetListData'])->name('asset.list');   // lazy-load khi mở tab Tài sản
            Route::get('/quan-ly-xe/{vehicle}/data',  [TruckingV2Controller::class, 'vehicleData'])->name('fleet.data');
            Route::get('/quan-ly-xe/{vehicle}/section/{section}', [TruckingV2Controller::class, 'vehicleSection'])->name('fleet.section');
        });
        // Stream file tập trung (disk-agnostic local/S3) — chỉ cần đăng nhập, phân quyền theo owner trong controller
        Route::get('/attachment/{attachment}', [TruckingV2Controller::class, 'showAttachment'])->name('attachment');
        Route::middleware('permission:settings.update')->group(function () {
            Route::put('/catalog/{type}',    [TruckingV2Controller::class, 'saveCatalog'])->name('catalog.save');
            Route::put('/customers',         [TruckingV2Controller::class, 'saveCustomers'])->name('customers.save');
            Route::put('/customer-rename',   [TruckingV2Controller::class, 'renameCustomer'])->name('customerRename');
            Route::put('/vehicles',          [TruckingV2Controller::class, 'saveVehicles'])->name('vehicles.save');
            Route::put('/settings',          [TruckingV2Controller::class, 'saveSettings'])->name('settings.save');
            Route::put('/route-fees',        [TruckingV2Controller::class, 'saveRouteFees'])->name('routeFees.save');
            Route::put('/fuel-prices',       [TruckingV2Controller::class, 'saveFuelPrices'])->name('fuelPrices.save');
            Route::put('/drivers',           [TruckingV2Controller::class, 'saveDrivers'])->name('drivers.save');
            Route::post('/drivers/{driver}/docs', [TruckingV2Controller::class, 'uploadDriverDocs'])->name('drivers.docs.upload');
            Route::delete('/drivers/{driver}/docs/{idx}', [TruckingV2Controller::class, 'deleteDriverDoc'])->name('drivers.docs.delete')->whereNumber('idx');
            Route::put('/quan-ly-xe/{vehicle}', [TruckingV2Controller::class, 'saveVehicle'])->name('fleet.save');
            Route::put('/quan-ly-xe/cost/{cost}/cancel', [TruckingV2Controller::class, 'adminCancelCost'])->name('fleet.cancelCost');
            Route::post('/quan-ly-xe-cost-item', [TruckingV2Controller::class, 'addVehicleCostItem'])->name('fleet.costItem');
            Route::post('/quan-ly-xe/{vehicle}/cost-photo', [TruckingV2Controller::class, 'uploadCostPhotos'])->name('fleet.costPhoto.upload');
            Route::post('/quan-ly-xe/{vehicle}/docs', [TruckingV2Controller::class, 'uploadVehicleDocs'])->name('fleet.docs.upload');
            Route::delete('/quan-ly-xe/{vehicle}/docs/{idx}', [TruckingV2Controller::class, 'deleteVehicleDoc'])->name('fleet.docs.delete')->whereNumber('idx');
            // Quản lý tài sản (kind='asset' — dùng chung route data/section/save/docs/cost ở trên)
            Route::post('/quan-ly-tai-san',          [TruckingV2Controller::class, 'createAsset'])->name('asset.create');
            Route::post('/quan-ly-tai-san-category', [TruckingV2Controller::class, 'addAssetCategory'])->name('asset.category');
            Route::delete('/quan-ly-tai-san/{vehicle}', [TruckingV2Controller::class, 'destroyAsset'])->name('asset.destroy');
        });
    });

    // ===== Users =====
    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        // Debug: test Reverb broadcast tới mọi user
        Route::post('/users/broadcast-test', [UserController::class, 'broadcastTest'])->name('users.broadcastTest');
    });
    Route::middleware('permission:users.create')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:users.update')->group(function () {
        Route::put ('/users/{user}',          [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/2fa/reset', [UserController::class, 'resetTwoFactor'])->name('users.2fa.reset');
    });
    Route::middleware('permission:users.delete')->group(function () {
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // ===== Tasks (Ghi chú & công việc) =====
    Route::middleware('permission:tasks.view')->group(function () {
        Route::get('/tasks',           [TaskController::class, 'index'])->name('tasks.index');
        Route::get('/tasks/{task}',    [TaskController::class, 'show'])->name('tasks.show');
    });
    Route::middleware('permission:tasks.create')->group(function () {
        Route::post('/tasks',                  [TaskController::class, 'store'])->name('tasks.store');
        Route::put ('/tasks/{task}',           [TaskController::class, 'update'])->name('tasks.update');
        Route::put ('/tasks/{task}/status',    [TaskController::class, 'toggleStatus'])->name('tasks.toggleStatus');
        Route::delete('/tasks/{task}',         [TaskController::class, 'destroy'])->name('tasks.destroy');

        // Comments
        Route::post  ('/tasks/{task}/comments',                   [TaskCommentController::class, 'store'])->name('tasks.comments.store');
        Route::delete('/tasks/{task}/comments/{comment}',         [TaskCommentController::class, 'destroy'])->name('tasks.comments.destroy');
    });

    // Endpoint cho mention picker (search users) — chỉ cần đã login
    Route::get('/api/users/search', function (Request $request) {
        $q = trim((string) $request->get('q', ''));
        return \App\Models\User::query()
            ->when($q, fn ($qb) => $qb->where('name', 'like', "%$q%"))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name']);
    })->name('users.search');

    // ===== Notifications =====
    Route::get   ('/notifications',                   [NotificationController::class, 'index'])->name('notifications.index');
    Route::get   ('/notifications/feed',              [NotificationController::class, 'feed'])->name('notifications.feed');
    Route::post  ('/notifications/{id}/read',         [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::post  ('/notifications/read-all',          [NotificationController::class, 'markAllRead'])->name('notifications.readAll');
    Route::delete('/notifications/{id}',              [NotificationController::class, 'destroy'])->name('notifications.destroy');

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

    // ===== Cài đặt hệ thống (chung) =====
    Route::middleware('permission:system.settings')->group(function () {
        Route::get ('/system-settings',      [SystemSettingController::class, 'index'])->name('system.settings');
        Route::put ('/system-settings',      [SystemSettingController::class, 'update'])->name('system.settings.update');
        Route::post('/system-settings/test', [SystemSettingController::class, 'test'])->name('system.settings.test');
        Route::post('/system-settings/backup', [SystemSettingController::class, 'backupNow'])->name('system.settings.backupNow');
        Route::get ('/system-settings/backup/{file}/download', [SystemSettingController::class, 'downloadBackup'])->name('system.settings.backupDownload');
    });
});
