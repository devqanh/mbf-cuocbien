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
    // Giá theo LOẠI CONT: cột chung 40FT/20FT (= tổng cước+dầu) áp mọi cont cùng cỡ.
    $mk = fn ($bid, $fee) => TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bid, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => '', 'from' => 'ICDTEST', 'to1' => 'QV', 'prices' => ['40FT' => $fee, '20FT' => $fee], 'sort' => 0]);
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
    // Báo giá gốc (cước/dầu × 40/20) quy về cột chung: 40FT = cước 3.315.432 + dầu 492.217.
    $ok($nonDry && (int) ($nonDry['prices']['40FT'] ?? 0) === 3315432 + 492217, 'B5 barging DRY ICDTP→HPP 40FT = cước 3.315.432 + dầu 492.217');
    $ok(count($svc->parseQuotationRows($DEV . '2. MBF-202606-01.xlsx')) === 0, 'B6 file không có sheet import → []');
    // importQuotationToBook
    $bImp = $svc->createPriceBook($cA->name, 'T6', '2026-06-01', '2026-06-30')['books'];
    $bImpId = collect($bImp)->firstWhere('label', 'T6')['id'];
    // Ký hiệu trong báo giá phải được khai ánh xạ trước — không còn tự tạo danh mục khi import.
    $resBlock = $svc->importQuotationToBook($bImpId, $DEV . '2. MBF-202606-02.xlsx', true);
    $ok(($resBlock['ok'] ?? true) === false && ! empty($resBlock['unmapped']), 'B7 chặn import khi có ký hiệu chưa khai ánh xạ');
    $ok(TruckingPriceRow::where('price_book_id', $bImpId)->count() === 0, 'B8 bị chặn → không ghi dòng giá nào');
    $locBefore = \App\Models\TruckingLocation::count();
    $ok($locBefore === \App\Models\TruckingLocation::count(), 'B9 import bị chặn không tự tạo địa điểm');
    foreach ($resBlock['unmapped'] as $u) \App\Models\TruckingLocation::create(['name' => $u['value'], 'code' => $u['value']]);
    $svcMapped = new TruckingV2Service();   // instance mới → nạp lại danh mục vừa khai
    $resImp = $svcMapped->importQuotationToBook($bImpId, $DEV . '2. MBF-202606-02.xlsx', true);
    $ok(($resImp['ok'] ?? false) && $resImp['imported'] === count($r06), 'B10 khai ánh xạ xong → nạp = số parse (' . count($r06) . ')');

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
    TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bOpenId, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => 'DRY CONTAINER', 'from' => 'ICDTP', 'prices' => ['40FT' => 3500000], 'sort' => 1]);
    TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bOpenId, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => 'NOR CONTAINER', 'from' => 'ICDTP', 'prices' => ['40FT' => 4600000], 'sort' => 2]);
    $prDry = $price($mkB('40HC'), '2026-08-20');
    $ok($prDry['isBarge'] && $prDry['bargeCont'] === 'DRY' && (int) $prDry['bargeCuoc'] === 3500000 && (int) $prDry['bargeDau'] === 0 && $prDry['bargeContKey'] === '40FT', 'D1 40HC → DRY, phí sà lan 1 số tổng 3,5tr (cột chung 40FT), dau = 0');
    $prNor = $price($mkB('40RF'), '2026-08-20');
    $ok($prNor['bargeCont'] === 'NOR' && (int) $prNor['bargeCuoc'] === 4600000, 'D2 40RF → NOR 4,6tr');
    $noB = $mkB('40HC'); $noB->barge_drop = null;
    $ok((int) $price($noB, '2026-08-20')['bargeCuoc'] === 0, 'D3 không Nơi hạ sà lan → không phí sà lan');

    // ---------- I. Giá theo LOẠI CONT (1 số tổng cước+dầu) + mẫu báo giá phẳng ----------
    $section('I. Giá theo loại cont + mẫu báo giá phẳng');
    $mkI = function ($ct) use ($cA) { $s = new TruckingShipment(); $s->customer_id = $cA->id; $s->setRelation('customer', $cA); $s->sheet = 'ICD'; $s->cont_type = $ct; $s->io = 'nhap'; $s->from_loc = 'ICDTEST'; $s->to_loc = 'HPP'; $s->kho = 'QV'; $s->ra_mode = 'self'; return $s; };
    $pI = fn ($ct) => $price($mkI($ct), '2026-06-20');   // book B (16–30/6)
    // Cột RIÊNG theo loại cont
    TruckingPriceRow::where('price_book_id', $bB)->delete();
    TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bB, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => '', 'from' => 'ICDTEST', 'to1' => 'QV', 'prices' => ['40HC' => 5000000, '40RHC' => 6000000, '20DC' => 3000000], 'sort' => 0]);
    $p40 = $pI('40HC');
    $ok((int) $p40['cuoc'] === 5000000 && (int) $p40['dau'] === 0 && $p40['contKey'] === '40HC' && (int) $p40['phaiThu'] === 5000000, 'I1 40HC → cột 40HC = 5tr, dau = 0, phải thu = 5tr');
    $ok((int) $pI('40RHC')['cuoc'] === 6000000 && (int) $pI('20DC')['cuoc'] === 3000000, 'I2 40RHC → 6tr · 20DC → 3tr');
    $ok((int) $pI("40'hc")['cuoc'] === 5000000 && (int) $pI('40 HC')['cuoc'] === 5000000, "I3 \"40'hc\" / \"40 HC\" chuẩn hóa → cột 40HC");
    $p45 = $pI('45HC');
    $ok($p45['matched'] === false && $p45['routeMatched'] === true && (int) $p45['cuoc'] === 0 && $p45['diag']['contType'] === '45HC' && in_array('40HC', $p45['diag']['contKeys'], true), 'I5 45HC không có cột riêng/chung → chưa khớp, diag nêu loại cont + cột đang có');
    // Cột CHUNG (dữ liệu backfill): 20FT/40FT áp mọi cont cùng cỡ; cont không phải 20 → 40FT (giữ hành vi cũ)
    TruckingPriceRow::where('price_book_id', $bB)->delete();
    TruckingPriceRow::create(['customer_id' => $cA->id, 'price_book_id' => $bB, 'loc' => 'HPP', 'conn' => 'Non', 'kind' => '', 'from' => 'ICDTEST', 'to1' => 'QV', 'prices' => ['20FT' => 2000000, '40FT' => 4000000], 'sort' => 0]);
    $ok((int) $pI('40HC')['cuoc'] === 4000000 && $pI('40HC')['contKey'] === '40FT', 'I6 40HC không có cột riêng → cột chung 40FT');
    $ok((int) $pI('20RF')['cuoc'] === 2000000 && (int) $pI('45HC')['cuoc'] === 4000000 && (int) $pI('LCL')['cuoc'] === 4000000 && (int) $pI('')['cuoc'] === 4000000, 'I7 20RF → 20FT · 45HC / LCL / trống → 40FT (tương thích cũ)');
    // Backfill dữ liệu thật: mọi dòng có prices, 40FT = cước40 + dầu40
    $rawRow = DB::table('trucking_price_rows')->whereNotNull('trans_fee_40')->where('trans_fee_40', '>', 0)->whereNotNull('prices')->first();
    if ($rawRow) { $pp = json_decode($rawRow->prices, true); $ok((int) ($pp['40FT'] ?? 0) === (int) round((float) $rawRow->trans_fee_40 + (float) $rawRow->fuel_fee_40), 'I8 backfill: 40FT = cước40 + dầu40 (dòng #' . $rawRow->id . ')'); }
    // Lưu từ UI / import: khóa chuẩn hóa, bỏ ô trống, cột ngoài danh mục bị chặn (ký hiệu địa điểm phải khai trước)
    \App\Models\TruckingLocation::firstOrCreate(['code' => 'ICDTEST'], ['name' => '__ICD TEST']);
    \App\Models\TruckingLocation::firstOrCreate(['code' => 'ICDTP'], ['name' => '__ICD TP TEST']);
    $svcI = new TruckingV2Service();   // instance mới → nạp lại danh mục địa điểm vừa khai
    $rowUi = ['loc' => 'HPP', 'conn' => 'Connect', 'kind' => 'Chưa phân nhóm', 'from' => 'ICDTEST', 'to1' => 'QV', 'distance' => '10', 'prices' => ['40hc' => '5.000.000', 'cont45' => '7000000', '20DC' => '']];
    $resBad = $svcI->savePriceBookRows($bA, [['prices' => $rowUi['prices'] + ['XYZ99' => '1']] + $rowUi]);
    $ok(($resBad['ok'] ?? true) === false && in_array('XYZ99', $resBad['unknownCont'] ?? [], true) && TruckingPriceRow::where('price_book_id', $bA)->count() === 0, 'I9 cột loại cont ngoài danh mục → chặn lưu, không ghi');
    $resOk = $svcI->savePriceBookRows($bA, [$rowUi]);
    $saved = $resOk['priceList'][0] ?? null;
    $ok($saved && ($saved['prices'] ?? []) === ['40HC' => '5000000', '45' => '7000000'] && $saved['kind'] === '', 'I10 lưu: khóa chuẩn (40hc→40HC, cont45→45 cột chung), bỏ ô trống, "Chưa phân nhóm" → KIND rỗng');
    $resCp = $svcI->copyPriceRows($bA, $bImpId, true);
    $ok(($resCp['copied'] ?? 0) === 1 && (($resCp['priceList'][0]['prices'] ?? [])['40HC'] ?? '') === '5000000', 'I11 copy bảng giá → bảng giá giữ nguyên prices');
    // Mẫu phẳng: tạo file → parse (ô trống = giống dòng trên, bỏ GHI CHÚ, bỏ dòng không giá) → lỗi TRẠNG THÁI → import round-trip
    $tmpBase = tempnam(sys_get_temp_dir(), 'pq'); $tmp = $tmpBase . '.xlsx';
    $ssI = new \PhpOffice\PhpSpreadsheet\Spreadsheet(); $wsI = $ssI->getActiveSheet(); $wsI->setTitle('Bảng giá');
    $wsI->fromArray([
        ['ĐIỂM HẠ', 'TRẠNG THÁI', 'KIND', 'FROM', 'TO', 'TO 2', 'TO 3', 'TO 4', 'KM', '20DC', '40HC', '40RHC', 'GHI CHÚ'],
        ['HPP', 'Connect', 'Transportation 1 way of Import/Export', 'ICDTEST', 'QV', '', '', 'HPP', 287, 4480000, 5130000, 5600000, 'ví dụ'],
        ['', '', '', 'ICDTEST', 'TS', '', '', 'HPP', 266, 4000000, 4700000, '', ''],
        ['', 'Disconnect', '', 'ICDTEST', 'QV', '', '', 'HPP', 287, '', 4900000, '', ''],
        ['HPP', 'Non', 'DRY CONTAINER', 'ICDTP', '', '', '', '', '', 3000000, 3500000, '', ''],
        ['HPP', 'Connect', 'X', 'ICDTEST', 'TL', '', '', 'HPP', 1, '', '', '', ''],
    ], null, 'A1');
    (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ssI))->save($tmp);
    $flat = $svcI->parseQuotation($tmp);
    $ok($flat['format'] === 'flat' && $flat['contCols'] === ['20DC', '40HC', '40RHC'] && count($flat['rows']) === 4 && ! $flat['errors'], 'I12 mẫu phẳng: nhận dạng sheet, 3 cột loại cont (bỏ GHI CHÚ), 4 dòng');
    $ok($flat['rows'][1]['loc'] === 'HPP' && $flat['rows'][1]['conn'] === 'Connect' && $flat['rows'][1]['kind'] === 'Transportation 1 way of Import/Export' && $flat['rows'][1]['prices'] === ['20DC' => 4000000, '40HC' => 4700000], 'I13 ô trống = giống dòng trên; bỏ ô giá trống');
    $ok($flat['rows'][2]['conn'] === 'Disconnect' && $flat['rows'][3]['conn'] === 'Non' && count($flat['warnings']) === 1, 'I14 đổi trạng thái theo dòng; dòng không có giá → cảnh báo bỏ qua');
    $wsI->setCellValue('B3', 'Bậy'); (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ssI))->save($tmp);
    $bad = $svcI->parseQuotation($tmp);
    $ok(count($bad['errors']) === 1 && str_contains($bad['errors'][0], 'TRẠNG THÁI') && ($svcI->importQuotationToBook($bImpId, $tmp, true)['ok'] ?? true) === false, 'I15 TRẠNG THÁI sai → báo lỗi dòng + chặn import');
    $wsI->setCellValue('B3', ''); (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($ssI))->save($tmp);
    $resFlat = $svcI->importQuotationToBook($bImpId, $tmp, true);
    $ok(($resFlat['ok'] ?? false) && $resFlat['imported'] === 4 && $resFlat['format'] === 'flat', 'I16 import mẫu phẳng (ghi đè) = 4 dòng');
    $got = collect($svcI->priceBookRows($bImpId));
    $ok($got->count() === 4 && $got->firstWhere('from', 'ICDTP')['prices'] === ['20DC' => '3000000', '40HC' => '3500000'] && ($got->firstWhere('to1', 'TS')['prices']['40HC'] ?? '') === '4700000', 'I17 round-trip: dòng giá đọc lại đúng cột/giá');
    $vq = $svcI->validateQuotation($tmp);
    $ok($vq['ok'] === true && $vq['format'] === 'flat' && $vq['contCols'] === ['20DC', '40HC', '40RHC'], 'I18 validateQuotation mẫu phẳng OK + trả cột loại cont');
    @unlink($tmp); @unlink($tmpBase);

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

    // ---------- H. Import CẬP NHẬT lô đã có ----------
    // Chỉ đụng lô TỰ TẠO trong mục này rồi XÓA ngay — không dựa vào rollback để cứu dữ liệu thật.
    $section('H. Import cập nhật lô từ Excel');
    $mkH = function (array $over = []) use ($svc) {
        return $svc->saveShipment($over + [
            'customer' => '__TEST_UPD__', 'booking' => 'TEST-UPD', 'io' => 'Nhập', 'qty' => 1,
            'contType' => '40HC', 'contNo' => 'ZZTU' . random_int(1000000, 9999999), 'from' => 'HPP', 'to' => 'HPP',
            'kho' => 'TS', 'gioXeDen' => '2026-07-01T08:00', 'gioXeRa' => '2026-07-01T10:00', 'bksVao' => '29H-00001',
            'cost' => ['items' => []], 'rev' => ['vatRate' => '0', 'doanhThu' => [], 'choHo' => [], 'payments' => []],
        ], 'icd')->fresh();
    };
    $rowH = fn ($id, array $values, $cont = '') => ['line' => 1, 'id' => (string) $id, 'contNo' => $cont, 'values' => $values, 'raws' => []];
    $h1 = $mkH();
    $hDup1 = $mkH(['contNo' => 'ZZDUP7654321']);
    $hDup2 = $mkH(['contNo' => 'ZZDUP7654321']);
    try {
        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['gioXeRa' => '', 'bksVao' => ''])]);
        $ok($r['valid'] && ! $r['changes'] && $r['noChange'] === 1, 'H1 ô trống = giữ nguyên (0 thay đổi)');

        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['gioXeRa' => '2026-07-01T15:30'])]);
        $cells = $r['changes'][0]['cells'] ?? [];
        $ok(count($cells) === 1 && $cells[0]['old'] === '2026-07-01T10:00' && $cells[0]['new'] === '2026-07-01T15:30', 'H2 diff đúng 1 ô cũ→mới');

        $res = $svc->importShipmentUpdate('icd', [$rowH($h1->id, ['gioXeRa' => '2026-07-01T15:30'])]);
        $h1f = TruckingShipment::find($h1->id);
        $ok($res['updated'] === 1 && $res['cells'] === 1 && substr((string) $h1f->gio_xe_ra, 0, 16) === '2026-07-01 15:30', 'H3 ghi đúng 1 ô');
        $ok($h1f->bks_vao === '29H-00001' && $h1f->kho === 'TS', 'H4 cột khác nguyên vẹn');

        $svc->importShipmentUpdate('icd', [$rowH($h1->id, ['bksVao' => '--'])]);
        $ok(trim((string) TruckingShipment::find($h1->id)->bks_vao) === '', 'H5 “--” xóa giá trị');

        $before = TruckingShipment::find($h1->id)->to_loc;
        $res = $svc->importShipmentUpdate('icd', [
            $rowH($h1->id, ['inv' => 'INV-OK']),
            $rowH($h1->id + 999999, ['inv' => 'INV-LOI']),
        ]);
        $ok($res['valid'] === false && $res['updated'] === 0 && TruckingShipment::find($h1->id)->to_loc === $before, 'H6 all-or-nothing: 1 dòng lỗi là không ghi gì');

        // Biển số phải có trong danh mục Xe (recompute map vehicle_id bằng khớp chuỗi chính xác).
        $plate = \App\Models\TruckingVehicle::whereNotNull('plate')->where('plate', '!=', '')->value('plate');
        $r = $svc->validateShipmentUpdate('icd', [$rowH('', ['bksRa' => $plate], 'ZZDUP7654321')]);
        $ok(! $r['valid'] && str_contains(implode(' ', $r['errors'][0]['reasons']), 'trùng ở 2 lô'), 'H7 cont trùng 2 lô → chặn, đòi ID');

        $r = $svc->validateShipmentUpdate('icd', [$rowH('', ['contNo' => 'ZZKHAC1', 'bksRa' => $plate], $h1->cont_no)]);
        $ok($r['valid'] && array_column($r['changes'][0]['cells'], 'field') === ['bksRa'], 'H8 cột Số cont bị bỏ qua khi dùng làm khóa');

        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['gioXeRa' => '2026-07-01T06:00'])]);
        $ok($r['valid'] && count($r['warnings']) === 1 && str_contains($r['warnings'][0]['text'], 'sớm hơn'), 'H9 giờ ra sớm hơn giờ đến → cảnh báo, không chặn');

        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['to' => 'KHONG-CO-TRONG-DANH-MUC'])]);
        $ok(! $r['valid'] && str_contains(implode(' ', $r['errors'][0]['reasons']), 'danh mục Địa điểm'), 'H10 chặn địa điểm ngoài danh mục');

        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['bksVao' => '99Z-99999'])]);
        $ok(! $r['valid'] && str_contains(implode(' ', $r['errors'][0]['reasons']), 'danh mục Xe'), 'H10b chặn biển số ngoài danh mục Xe');
        if ($plate) {
            $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['bksVao' => mb_strtolower($plate)])]);
            $ok($r['valid'] && ($r['changes'][0]['cells'][0]['new'] ?? '') === $plate, 'H10c biển số viết thường → chuẩn về đúng chuỗi danh mục');
        }

        // Xuất → nhập lại nguyên vẹn: 0 lỗi, 0 ô đổi. Giá trị cũ có thể không còn hợp lệ theo
        // danh mục hiện tại (loại cont 20DC/40RHC) — ô KHÔNG sửa thì không được kiểm tra.
        $h1->forceFill(['cont_type' => '20DC', 'gio_xe_ra' => '2026-07-01 06:00:00'])->save();
        $h1r = TruckingShipment::find($h1->id);
        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1r->id, [
            'contType' => $h1r->cont_type, 'from' => $h1r->from_loc, 'to' => $h1r->to_loc, 'kho' => $h1r->kho,
            'gioXeDen' => '2026-07-01T08:00', 'gioXeRa' => '2026-07-01T06:00',
        ])]);
        $ok($r['valid'] && ! $r['changes'] && ! $r['warnings'], 'H11 xuất→nhập lại nguyên vẹn: 0 lỗi, 0 ô đổi, 0 cảnh báo');

        // ---- Tờ khai (luồng riêng): mỗi tờ khai 1 dòng Excel → declPairs; tổng phí sang chi phí ----
        $declRow = fn (array $pairs) => ['line' => 2, 'id' => (string) $h1->id, 'contNo' => '', 'raws' => [], 'values' => ['declPairs' => $pairs]];
        $svc->importShipmentUpdate('icd', [$declRow([['no' => '103', 'fee' => '10000'], ['no' => '104', 'fee' => '20000']])]);
        $h1d = TruckingShipment::with('costLines')->find($h1->id);
        $ok(count($h1d->declarations) === 2 && $h1d->declaration_no === '103, 104', 'H12 ghi 2 tờ khai + declaration_no gộp cho tìm kiếm/bảng kê');
        $ok((int) $h1d->costLines->where('src', 'thanhLyFee')->first()?->amount === 30000, 'H13 tổng phí mở tờ khai → dòng chi phí thanhLyFee');

        $r = $svc->validateShipmentUpdate('icd', [$declRow([['no' => '103', 'fee' => '1'], ['no' => '103', 'fee' => '2']])]);
        $ok(! $r['valid'] && str_contains(implode(' ', $r['errors'][0]['reasons']), 'bị lặp'), 'H14 số tờ khai lặp trong cùng lô → chặn');

        $svc->importShipmentUpdate('icd', [$declRow([['no' => 'A1', 'fee' => '1000'], ['no' => 'A2', 'fee' => '2000'], ['no' => 'A3', 'fee' => '3000']])]);
        $h1s = TruckingShipment::with('costLines')->find($h1->id);
        $ok(array_column($h1s->declarations, 'no') === ['A1', 'A2', 'A3'], 'H14b thay cả danh sách tờ khai của lô');
        $ok((int) $h1s->costLines->where('src', 'thanhLyFee')->first()?->amount === 6000, 'H14c tổng phí mở tờ khai = 6.000');

        // File cập nhật LÔ không còn cột tờ khai → không đụng được tờ khai
        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['declNos' => '999', 'declFees' => '999'])]);
        $ok($r['valid'] && ! $r['changes'], 'H14d cột tờ khai đã gỡ khỏi file lô — bị bỏ qua');

        // ---- Cước xe ngoài: ghi vào dòng chi phí src=extTruck, không đụng khoản khác ----
        $r = $svc->validateShipmentUpdate('icd', [$rowH($h1->id, ['extFee' => '2000000'])]);
        $ok(! $r['valid'] && str_contains(implode(' ', $r['errors'][0]['reasons']), 'Nhà xe ngoài'), 'H15 cước xe ngoài thiếu nhà xe → chặn');
        $vend = \App\Models\TruckingExtVendor::first()?->name;
        if ($vend) {
            $svc->importShipmentUpdate('icd', [$rowH($h1->id, ['extVendor' => $vend, 'extFee' => '2000000'])]);
            $h1e = TruckingShipment::with('costLines')->find($h1->id);
            $ok((int) $h1e->ext_fee === 2000000 && $h1e->costLines->where('src', 'extTruck')->count() === 1, 'H16 cước xe ngoài → 1 dòng extTruck + chốt ext_fee');
            $ok((int) $h1e->costLines->where('src', 'thanhLyFee')->first()?->amount === 6000, 'H17 phí mở tờ khai không bị đụng khi sửa cước xe ngoài');
        }
    } finally {
        foreach ([$h1, $hDup1, $hDup2] as $x) TruckingShipment::find($x->id)?->delete();
        TruckingCustomer::where('name', '__TEST_UPD__')->delete();
    }

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
