<?php

/**
 * Probe hiệu năng (dev-only) — đo trang /trucking-v2/lo-hang render ra bao nhiêu HTML
 * và bao nhiêu tài nguyên trình duyệt phải nạp.
 *
 * Chạy:  php dev/perf_probe_page.php
 */

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\User;
use App\Support\ShipmentColumns;
use Illuminate\Support\Facades\DB;

$user = User::query()->get()->first(fn (User $u) => $u->can('shipments.view')) ?? User::query()->first();
auth()->login($user);

$svc = app(App\Services\TruckingV2Service::class);

DB::flushQueryLog();
DB::enableQueryLog();
$t = microtime(true);
$html = view('trucking2.lo-hang', [
    'canEdit'   => $user->can('shipments.update'),
    'canDelete' => $user->can('shipments.delete'),
    'boot'      => [
        'page' => $svc->pagedShipments('icd', []),
        'cfg'  => $svc->shipmentBoardConfig(),
        'sibs' => $svc->siblingsList('icd'),
    ],
    'cols' => ShipmentColumns::allowed(),
])->render();
$ms = (microtime(true) - $t) * 1000;

printf("render HTML : %.0f ms · %d queries · %.0f KB\n", $ms, count(DB::getQueryLog()), strlen($html) / 1024);

// Kích thước từng khối trong HTML
if (preg_match('/window\.__TRK\s*=\s*(\{.*?\});\s*\n<\/script>/s', $html, $m)) {
    printf("  boot JSON (window.__TRK) : %.0f KB\n", strlen($m[1]) / 1024);
}

// Tài nguyên trình duyệt phải nạp
preg_match_all('/<script[^>]*src="([^"]+)"/i', $html, $s);
preg_match_all('/<link[^>]*href="([^"]+)"/i', $html, $l);
echo "\n--- <script src> (".count($s[1]).") ---\n";
foreach ($s[1] as $u) echo '  '.$u."\n";
echo "--- <link href> (".count($l[1]).") ---\n";
foreach ($l[1] as $u) echo '  '.$u."\n";

// Thứ tự thực thi: script classic KHÔNG defer = chặn parser
preg_match_all('/<script(?![^>]*\bdefer\b)(?![^>]*\btype="module")[^>]*src="([^"]+)"/i', $html, $blocking);
printf("\nscript CHẶN render (không defer/module): %d\n", count($blocking[1]));
