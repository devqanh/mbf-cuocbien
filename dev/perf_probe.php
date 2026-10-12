<?php

/**
 * Probe hiệu năng (dev-only) — đo số query + thời gian của luồng tải trang Lô hàng.
 *
 * Chạy:  php dev/perf_probe.php
 *
 * Đo 3 thứ mà MỘT lần xem trang /trucking-v2/lo-hang thực sự tốn:
 *   1. pagedShipments('icd', [])  — render server-side (nhồi vào boot JSON) VÀ
 *   2. pagedShipments('icd', [...]) qua XHR /trucking-v2/shipments-page (client load lại)
 *   3. siblingsList('icd') + normalizedCodeMap (danh mục địa điểm/kho)
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TruckingShipment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Đăng nhập 1 user có quyền xem lô hàng để ShipmentColumns::can() hoạt động thật. */
$user = User::query()->get()->first(fn (User $u) => $u->can('shipments.view')) ?? User::query()->first();
if ($user) {
    auth()->login($user);
}
echo 'user                : '.($user?->name ?? '(none)')."\n";

/** @return array{0:float,1:int} [ms, số query] */
function probe(string $label, callable $fn): array
{
    DB::flushQueryLog();
    DB::enableQueryLog();
    $t = microtime(true);
    $out = $fn();
    $ms = (microtime(true) - $t) * 1000;
    $log = DB::getQueryLog();
    usort($log, fn ($a, $b) => $b['time'] <=> $a['time']);

    printf("\n== %s ==\n  %.0f ms · %d queries · peak %.1f MB\n", $label, $ms, count($log), memory_get_peak_usage(true) / 1048576);
    foreach (array_slice($log, 0, 5) as $q) {
        printf("  %6.0f ms  %s\n", $q['time'], mb_substr(preg_replace('/\s+/', ' ', $q['query']), 0, 150));
    }

    return [$ms, count($log)];
}

echo "\n--- Kích thước dữ liệu ---\n";
foreach (['trucking_shipments', 'trucking_cost_lines', 'trucking_revenue_lines', 'trucking_locations', 'trucking_warehouses', 'trucking_customers'] as $t) {
    printf("  %-26s %s rows\n", $t, number_format((int) DB::table($t)->count(), 0, ',', '.'));
}
printf("  %-26s %s rows (sheet ICD)\n", 'trucking_shipments(ICD)', number_format((int) TruckingShipment::ofSheet('icd')->count(), 0, ',', '.'));

$svc = app(App\Services\TruckingV2Service::class);

// 1) Render server-side (ShipmentController::index → boot JSON)
probe('pagedShipments("icd", [])  ← render HTML (boot)', fn () => $svc->pagedShipments('icd', []));

// 2) XHR /trucking-v2/shipments-page — y hệt lần 1 (user có bộ lọc lưu)
probe('pagedShipments("icd", [page=1, filter=all]) ← XHR', fn () => $svc->pagedShipments('icd', ['page' => 1, 'filter' => 'all']));

// 3) Phụ trợ không đổi theo tham số
probe('siblingsList("icd")', fn () => $svc->siblingsList('icd'));
probe('shipmentBoardConfig()', fn () => $svc->shipmentBoardConfig());

// 4) Toàn bộ danh sách anh em (đo zero-index scan DISTINCT to_loc/from_loc)
probe('DISTINCT to_loc/from_loc (2 lần trong pagedShipments)', function () {
    TruckingShipment::ofSheet('icd')->whereNotNull('to_loc')->where('to_loc', '!=', '')->distinct()->pluck('to_loc');
    TruckingShipment::ofSheet('icd')->whereNotNull('from_loc')->where('from_loc', '!=', '')->distinct()->pluck('from_loc');

    return null;
});
