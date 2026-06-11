<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PayableReportController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\ShipmentController;
use App\Http\Controllers\TaskCommentController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\TruckingController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'trucking.index' : 'login');
});

// ===== Tài liệu Trucking — CÔNG KHAI (không cần đăng nhập) để gửi kế toán =====
Route::get('/tailieu',          [TruckingController::class, 'docs'])->name('trucking.docs');
Route::get('/tailieu/download', [TruckingController::class, 'docsDownload'])->name('trucking.docsDownload');
Route::post('/tailieu/notes',   [TruckingController::class, 'saveNotes'])->name('trucking.saveNotes');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'showLogin'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ===== Profile (mọi user đã login) =====
    Route::get('/profile',           [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile/info',      [ProfileController::class, 'updateInfo'])->name('profile.info');
    Route::put('/profile/password',  [ProfileController::class, 'updatePassword'])->name('profile.password');

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

    // ===== Trucking (2 sheet HẠ HPH + HẠ ICD) — tái dùng quyền shipments.* =====
    Route::middleware('permission:shipments.view')->group(function () {
        Route::get('/trucking',      [TruckingController::class, 'index'])->name('trucking.index');
        Route::get('/trucking/data', [TruckingController::class, 'data'])->name('trucking.data');
        Route::put('/me/trucking-column-prefs', [TruckingController::class, 'updateColumnPrefs'])->name('trucking.columnPrefs');
    });
    Route::middleware('permission:shipments.update')->group(function () {
        Route::post('/trucking/bulk',           [TruckingController::class, 'bulk'])->name('trucking.bulk');
        Route::post('/trucking/reset-snapshot', [TruckingController::class, 'resetSnapshot'])->name('trucking.resetSnapshot');
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
        // Debug: test Reverb broadcast tới mọi user
        Route::post('/users/broadcast-test', [UserController::class, 'broadcastTest'])->name('users.broadcastTest');
    });
    Route::middleware('permission:users.create')->group(function () {
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
    });
    Route::middleware('permission:users.update')->group(function () {
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::put('/users/{user}/column-permissions', [UserController::class, 'updateColumnPermissions'])->name('users.columnPermissions');
        Route::put('/users/{user}/trucking-column-permissions', [UserController::class, 'updateTruckingColumnPermissions'])->name('users.truckingColumnPermissions');
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
});
