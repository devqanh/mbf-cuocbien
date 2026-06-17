<?php
// Kiem tra tham chieu thuc te de quyet dinh co can backfill/dedupe khong.
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TruckingCustomer;
use App\Models\TruckingShipment;
use App\Models\TruckingLocation;

echo "=== Customer ===\n";
echo "Tong: " . TruckingCustomer::count() . "\n";

$dups = TruckingCustomer::select('id', 'name')->get()
    ->groupBy(fn ($c) => mb_strtolower(preg_replace('/\s+/u', ' ', trim($c->name))))
    ->filter(fn ($g) => $g->count() > 1);
echo "Nhom dup (sau collapse whitespace + lowercase): " . $dups->count() . "\n";
foreach ($dups as $key => $g) {
    echo "  [{$key}] => " . $g->map(fn ($c) => "#{$c->id}:'{$c->name}'")->implode(' | ') . "\n";
}

echo "\n=== Shipments ===\n";
echo "Tong: " . TruckingShipment::count() . "\n";
echo "from_loc rong: " . TruckingShipment::where(fn ($q) => $q->whereNull('from_loc')->orWhere('from_loc', ''))->count() . "\n";
echo "to_loc rong:   " . TruckingShipment::where(fn ($q) => $q->whereNull('to_loc')->orWhere('to_loc', ''))->count() . "\n";

$nfFrom = TruckingShipment::whereNotNull('from_loc')->where('from_loc', '!=', '')->whereNull('from_location_id')->count();
$nfTo = TruckingShipment::whereNotNull('to_loc')->where('to_loc', '!=', '')->whereNull('to_location_id')->count();
echo "from_loc co data NHUNG from_location_id NULL: {$nfFrom}\n";
echo "to_loc co data NHUNG to_location_id NULL:     {$nfTo}\n";

echo "\n=== Location catalog ===\n";
echo "Tong: " . TruckingLocation::count() . "\n";

$locCodes = TruckingLocation::get(['code', 'name'])
    ->flatMap(fn ($l) => [trim((string) $l->code), trim((string) $l->name)])
    ->filter(fn ($v) => $v !== '')
    ->map(fn ($v) => mb_strtolower($v))
    ->unique()
    ->values()
    ->all();
$locSet = array_flip($locCodes);

$missingFrom = TruckingShipment::whereNotNull('from_loc')->where('from_loc', '!=', '')
    ->distinct()->pluck('from_loc')
    ->map(fn ($v) => trim((string) $v))->filter()
    ->reject(fn ($v) => isset($locSet[mb_strtolower($v)]))
    ->values();
echo "from_loc value KHONG co trong danh muc Location: " . $missingFrom->count() . "\n";
foreach ($missingFrom->take(30) as $v) echo "  '{$v}'\n";

$missingTo = TruckingShipment::whereNotNull('to_loc')->where('to_loc', '!=', '')
    ->distinct()->pluck('to_loc')
    ->map(fn ($v) => trim((string) $v))->filter()
    ->reject(fn ($v) => isset($locSet[mb_strtolower($v)]))
    ->values();
echo "to_loc value KHONG co trong danh muc Location:   " . $missingTo->count() . "\n";
foreach ($missingTo->take(30) as $v) echo "  '{$v}'\n";
