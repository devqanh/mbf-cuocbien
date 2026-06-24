<?php
/**
 * KIỂM THỬ CHUYÊN SÂU các tính năng vừa triển khai — transaction-rollback, KHÔNG ghi DB thật.
 * Chạy: php artisan tinker --execute="require '<path>/test_all.php';"
 */
use Illuminate\Support\Facades\DB;
use App\Models\TruckingShipment;
use App\Models\TruckingCustomer;
use App\Models\TruckingPriceBook;
use App\Models\TruckingPriceRow;
use App\Models\TruckingCostLine;
use App\Models\TruckingCostItem;
use App\Services\TruckingV2Service;
use App\Services\Trucking\Concerns\HandlesStatements;

$svc = app(TruckingV2Service::class);
$R = function ($m) use ($svc) { $x = new ReflectionMethod($svc, $m); $x->setAccessible(true); return $x; };
$priceShipment        = $R('priceShipment');
$pricingContextForDate = $R('pricingContextForDate');
$pickPriceBook        = $R('pickPriceBook');
// Reset memoize-cache (per-request) — TEST tạo book/rows giữa chừng nên phải xoá cache để thấy thay đổi.
// (Thực tế định giá chạy ở request MỚI sau khi đã lưu book → không gặp vấn đề này.)
$resetCache = function () use ($svc) {
    foreach (['pricingCtxCache' => [], 'priceBooksCache' => []] as $p => $v) {
        try { $rp = new ReflectionProperty($svc, $p); $rp->setAccessible(true); $rp->setValue($svc, $v); } catch (\Throwable $e) {}
    }
};
$price = function ($s, $date) use ($svc, $priceShipment, $pricingContextForDate, $resetCache) {
    $resetCache();
    return $priceShipment->invoke($svc, $s, $pricingContextForDate->invoke($svc, $s->customer_id ? (int) $s->customer_id : null, optional($s->customer)->name ?? '', $date));
};

$pass = 0; $fail = 0; $fails = [];
$ok = function ($cond, $label) use (&$pass, &$fail, &$fails) {
    if ($cond) { $pass++; echo "  ✓ $label\n"; }
    else { $fail++; $fails[] = $label; echo "  ✗ FAIL: $label\n"; }
};
$section = fn ($t) => print("\n=== $t ===\n");
$DEV = base_path('dev') . '/';
$custName = TruckingCustomer::value('name');
$custId   = (int) TruckingCustomer::where('name', $custName)->value('id');

DB::beginTransaction();
try {
    // ---------- A. Bảng giá theo khoảng ngày ----------
    $section('A. Bảng giá theo khoảng ngày');
    $cA = TruckingCustomer::create(['name' => '__TEST_PB__']);
    // createPriceBook trả books SẮP theo period_from → phải lấy theo LABEL, không phải [0].
    $bA = collect($svc->createPriceBook($cA->name, 'A', '2026-06-01', '2026-06-15')['books'])->firstWhere('label', 'A')['id'];
    $bB = collect($svc->createPriceBook($cA->name, 'B', '2026-06-16', '2026-06-30')['books'])->firstWhere('label', 'B')['id'];
    $mk = fn ($bid, $fee) => TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bid, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => '', 'from' => 'ICDTEST', 'to1' => 'QV', 'trans_fee_40' => $fee, 'trans_fee_20' => $fee, 'fuel_fee_40' => 0, 'fuel_fee_20' => 0, 'sort' => 0]);
    $mk($bA, 1000000); $mk($bB, 2000000);
    $mkShip = function ($d) use ($cA) { $s = new TruckingShipment(); $s->customer_id = $cA->id; $s->setRelation('customer', $cA); $s->sheet = 'ICD'; $s->cont_type = '40'; $s->io = 'nhap'; $s->from_loc = 'ICDTEST'; $s->to_loc = 'HPP'; $s->kho = 'QV'; $s->ra_mode = 'self'; return $s; };
    $ok((int) $price($mkShip('2026-06-05'), '2026-06-05')['cuoc'] === 1000000, 'A1 ngày 5/6 → book A (1.000.000)');
    $ok((int) $price($mkShip('2026-06-20'), '2026-06-20')['cuoc'] === 2000000, 'A2 ngày 20/6 → book B (2.000.000)');
    $ok($price($mkShip('2026-08-20'), '2026-08-20')['matched'] === false, 'A3 ngày ngoài kỳ + không book mở → chưa khớp');
    // ưu tiên book có ngày thắng book mở
    $bOpen = $svc->createPriceBook($cA->name, 'Mở', null, null)['books']; $bOpenId = collect($bOpen)->firstWhere('label', 'Mở')['id'];
    $mk($bOpenId, 9999999);
    $ok((int) $price($mkShip('2026-06-05'), '2026-06-05')['cuoc'] === 1000000, 'A4 trong A + có book mở → vẫn chọn A (cụ thể thắng mở)');
    $ok((int) $price($mkShip('2026-08-20'), '2026-08-20')['cuoc'] === 9999999, 'A5 ngoài kỳ → rơi về book mở');
    // CRUD: savePriceBookRows chỉ đụng book đó
    $svc->savePriceBookRows($bA, []); // xóa rows book A
    $ok(TruckingPriceRow::where('price_book_id', $bA)->count() === 0 && TruckingPriceRow::where('price_book_id', $bB)->count() === 1, 'A6 savePriceBookRows chỉ đụng book A, book B nguyên');
    // backward-compat khách thật: book mặc định phủ mọi ngày
    $bk = $svc->extStatementsForList ? true : true; // noop
    $real = TruckingShipment::where('customer_id', $custId)->whereNotNull('gio_xe_ra')->first();
    if ($real) { $pr = $price($real, $svc->outDate ?? null); }
    $ok(TruckingPriceBook::where('customer_id', $custId)->whereNull('period_from')->whereNull('period_to')->exists(), 'A7 khách thật có book "Mặc định (mọi ngày)" (backfill)');

    // ---------- B. Import báo giá gốc ----------
    $section('B. Import báo giá gốc (parseQuotationRows)');
    $r06 = $svc->parseQuotationRows($DEV . '2. MBF-202606-02.xlsx');
    $r07 = $svc->parseQuotationRows($DEV . '2. MBF-202607-00.xlsx');
    $cnt = function ($rows, $loc, $conn, $kind) { return collect($rows)->where('loc', $loc)->where('conn', $conn)->where('kind', $kind)->count(); };
    $ok($cnt($r07, 'ICD TP', 'Connect', 'Transportation 1 way of Import/Export') === 14, 'B1 202607 ICD TP/Connect 1way = 14');
    $ok($cnt($r07, 'ICD TP', 'Connect', 'Internal CRU transportation') === 18, 'B2 Internal CRU = 18');
    $ok($cnt($r07, 'ICD TP', 'Connect', 'External CRU transportation') === 9, 'B3 External CRU = 9');
    $ok(collect($r07)->filter(fn ($x) => trim($x['kind']) === '' || $x['kind'] === '-' || strpos($x['kind'], "\n") !== false)->isEmpty(), 'B4 không KIND rỗng/-/xuống dòng');
    $nonDry = collect($r06)->firstWhere(fn ($x) => $x['conn'] === 'Non' && $x['kind'] === 'DRY CONTAINER' && $x['loc'] === 'HPP');
    $ok($nonDry && (int) $nonDry['transFee40'] === 3315432, 'B5 barging DRY ICDTP→HPP = 3.315.432');
    $ok(count($svc->parseQuotationRows($DEV . '2. MBF-202606-01.xlsx')) === 0, 'B6 file không có sheet import → []');
    // importQuotationToBook
    $bImp = $svc->createPriceBook($cA->name, 'T6', '2026-06-01', '2026-06-30')['books'];
    $bImpId = collect($bImp)->firstWhere('label', 'T6')['id'];
    $resImp = $svc->importQuotationToBook($bImpId, $DEV . '2. MBF-202606-02.xlsx', true);
    $ok(($resImp['ok'] ?? false) && $resImp['imported'] === count($r06), 'B7 importQuotationToBook nạp = số parse (' . count($r06) . ')');

    // ---------- C. Chi phí auto + VAT ----------
    $section('C. Khoản chi phí auto + VAT (net)');
    $ok((new TruckingCostLine(['amount' => 110000, 'vat' => 10]))->netAmount() == 100000, 'C1 110k@10% → net 100k');
    $ok((new TruckingCostLine(['amount' => 108000, 'vat' => 8]))->netAmount() == 100000, 'C2 108k@8% → net 100k');
    $ok((new TruckingCostLine(['amount' => 50000, 'vat' => 0]))->netAmount() == 50000, 'C3 0% → giữ 50k');
    $sC = TruckingShipment::where('customer_id', $custId)->first();
    $sC->costLines()->delete();
    $sC->costLines()->create(['item' => 'X', 'amount' => 110000, 'vat' => 10, 'billable' => false, 'sort' => 0]);
    $sC->costLines()->create(['item' => 'Y', 'amount' => 55000, 'vat' => 10, 'billable' => true, 'sort' => 1]);
    $svc->recomputeShipmentDerived($sC->fresh(), null); $sC->refresh();
    $ok(round($sC->cost_total) == 150000 && round($sC->cost_company) == 100000, 'C4 recompute cost_total/company = NET (150k/100k)');

    // ---------- D. Sà lan ----------
    $section('D. Sà lan (barge)');
    $mkB = function ($ct) use ($cA) { $s = new TruckingShipment(); $s->customer_id = $cA->id; $s->setRelation('customer', $cA); $s->sheet = 'ICD'; $s->cont_type = $ct; $s->io = 'nhap'; $s->to_loc = 'ICDTP'; $s->barge_drop = 'HPP'; $s->ra_mode = 'self'; return $s; };
    // tạo book mở có dòng Non DRY/NOR cho cA (route ICDTP→HPP)
    TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bOpenId, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => 'DRY CONTAINER', 'from' => 'ICDTP', 'trans_fee_40' => 3000000, 'trans_fee_20' => 0, 'fuel_fee_40' => 500000, 'fuel_fee_20' => 0, 'sort' => 1]);
    TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bOpenId, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => 'NOR CONTAINER', 'from' => 'ICDTP', 'trans_fee_40' => 4000000, 'trans_fee_20' => 0, 'fuel_fee_40' => 600000, 'fuel_fee_20' => 0, 'sort' => 2]);
    $prDry = $price($mkB('40HC'), '2026-08-20');
    $ok($prDry['isBarge'] && $prDry['bargeCont'] === 'DRY' && (int) $prDry['bargeCuoc'] === 3000000 && (int) $prDry['bargeDau'] === 500000, 'D1 40HC → DRY, cước+dầu sà lan 3tr/500k');
    $prNor = $price($mkB('40RF'), '2026-08-20');
    $ok($prNor['bargeCont'] === 'NOR' && (int) $prNor['bargeCuoc'] === 4000000, 'D2 40RF → NOR 4tr');
    $noB = $mkB('40HC'); $noB->barge_drop = null;
    $ok((int) $price($noB, '2026-08-20')['bargeCuoc'] === 0, 'D3 không Nơi hạ sà lan → không phí sà lan');

    // ---------- E. Lô hàng list ----------
    $section('E. Lô hàng (pagedShipments / bulk)');
    $p20 = $svc->pagedShipments('icd', []);
    $ok(($p20['perPage'] ?? 0) === 20, 'E1 perPage mặc định 20');
    $p100 = $svc->pagedShipments('icd', ['perPage' => 100]);
    $ok(($p100['perPage'] ?? 0) === 100, 'E2 perPage=100');
    $ok(($svc->pagedShipments('icd', ['perPage' => 999])['perPage'] ?? 0) === 20, 'E3 perPage ngoài whitelist → 20 (không lỗi)');
    $notout = $svc->pagedShipments('icd', ['filter' => 'notout', 'all' => 1]);
    $ok(collect($notout['data'])->every(fn ($x) => empty($x['gioXeRa'])), 'E4 filter notout → toàn lô chưa ra');
    $withFee = collect($p100['data'])->firstWhere(fn ($x) => $x['cuocDau'] !== null);
    $ok($withFee !== null, 'E5 có cột cuocDau cho lô đã ra');
    if ($withFee) { $sE = TruckingShipment::find($withFee['id']); $d = $svc->outDate ?? null; $prE = $price($sE, \Carbon\Carbon::parse($sE->gio_xe_ra)->toDateString());
        $expect = (int) $prE['cuoc'] + (int) $prE['dau'] + (int) ($prE['bargeCuoc'] ?? 0) + (int) ($prE['bargeDau'] ?? 0);
        $ok((int) $withFee['cuocDau'] === $expect, 'E6 cuocDau KHỚP priceShipment (nền cước+dầu+sà lan)'); }
    // bulk update
    $ids = TruckingShipment::where('customer_id', $custId)->limit(2)->pluck('id')->all();
    $n = $svc->bulkUpdateShipments($ids, ['to' => 'ICDTP', 'bargeDrop' => 'HPP']);
    $sb = TruckingShipment::find($ids[0]);
    $ok($n === 2 && $sb->to_loc === 'ICDTP' && $sb->barge_drop === 'HPP' && (bool) $sb->is_barge === true, 'E7 bulkUpdate áp to+bargeDrop + derive is_barge');

    // ---------- F. Bảng kê khách VAT ----------
    $section('F. Bảng kê khách: VAT% + cột');
    $lines = [['cuoc' => 800000, 'dau' => 200000, 'chiHo' => 300000], ['cuoc' => 500000, 'dau' => 0, 'bargeCuoc' => 0, 'chiHo' => 0]];
    $a8 = TruckingV2Service::statementAmounts($lines, 8);
    $ok($a8['base'] === 1500000 && $a8['vat'] === 120000 && $a8['choho'] === 300000 && $a8['total'] === 1920000, 'F1 vat8: nền1.5tr+VAT120k+chihộ300k=1.920tr');
    $a0 = TruckingV2Service::statementAmounts($lines, 0);
    $ok($a0['total'] === 1800000 && $a0['vat'] === 0, 'F2 vat0: total=nền+chi hộ (backward-compat)');
    // Σ per-line == tổng
    $sum = collect($lines)->reduce(fn ($c, $l) => $c + TruckingV2Service::statementAmounts([$l], 8)['total'], 0);
    $ok($sum === $a8['total'], 'F3 Σ per-line(8%) == tổng');

    // ---------- G. Đơn vị xe ngoài + Bảng kê xe ngoài ----------
    $section('G. Đơn vị xe ngoài + Bảng kê xe ngoài (payable)');
    // saveShipment chốt ext_fee + chặn thiếu vendor
    $sG = TruckingShipment::where('customer_id', $custId)->first();
    $svc->saveShipment(['extVendor' => 'NX TEST', 'cost' => ['items' => [['src' => 'extTruck', 'item' => 'Cước xe ngoài', 'amount' => '2000000', 'payer' => 'Xe ngoài']]]], $sG->sheet, $sG, ['extVendor', 'cost']);
    $sG->refresh();
    $ok($sG->ext_vendor === 'NX TEST' && (int) $sG->ext_fee === 2000000, 'G1 saveShipment chốt ext_fee=2tr');
    $blocked = false; try { $svc->saveShipment(['extVendor' => '', 'cost' => ['items' => [['src' => 'extTruck', 'amount' => '100']]]], $sG->sheet, $sG, ['extVendor', 'cost']); } catch (\Throwable $e) { $blocked = true; }
    $ok($blocked, 'G2 chặn lưu thuê xe ngoài thiếu nhà xe');
    // candidates + statement
    $g = TruckingShipment::where('customer_id', $custId)->limit(3)->get();
    $g[0]->forceFill(['ext_vendor' => 'NX A', 'ext_fee' => 2000000, 'gio_xe_den' => '2026-06-10 08:00'])->save();
    $g[1]->forceFill(['ext_vendor' => 'NX A', 'ext_fee' => 1500000, 'gio_xe_den' => '2026-06-12 08:00'])->save();
    $g[2]->forceFill(['ext_vendor' => 'NX A', 'ext_fee' => 9000000, 'gio_xe_den' => '2026-07-20 08:00'])->save(); // ngoài kỳ
    $cand = $svc->extStatementCandidates('NX A', '2026-06-01', '2026-06-30');
    $ok(count($cand['candidates']) === 2, 'G3 candidates lọc nhà xe + Giờ xe đến ∈ kỳ = 2');
    $st = $svc->saveExtStatement(['no' => 'BKXN-T', 'vendor' => 'NX A', 'date' => '2026-06-30', 'from' => '2026-06-01', 'to' => '2026-06-30', 'lines' => array_map(fn ($x) => ['id' => $x['id'], 'fee' => $x['fee'], 'booking' => $x['booking'], 'date' => $x['date']], $cand['candidates']), 'payments' => [['date' => '2026-07-01', 'amount' => 2500000]]]);
    $arr = $svc->extStatementToArray($st->fresh(['lines', 'payments']));
    $ok((int) $arr['total'] === 3500000 && (int) $arr['paid'] === 2500000 && (int) $arr['conNo'] === 1000000, 'G4 total 3.5tr − đã trả 2.5tr = công nợ 1tr');

    echo "\n========================================\n";
    echo "TỔNG: PASS $pass · FAIL $fail\n";
    if ($fail) echo "LỖI: \n  - " . implode("\n  - ", $fails) . "\n";
    else echo "TẤT CẢ PASS ✓\n";
} catch (\Throwable $e) {
    echo "\n!! EXCEPTION: " . $e->getMessage() . "\n  " . $e->getFile() . ':' . $e->getLine() . "\n";
} finally {
    DB::rollBack();
    echo "[ROLLED BACK — không ghi DB thật]\n";
}
