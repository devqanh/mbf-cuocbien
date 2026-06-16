<?php

namespace App\Console\Commands;

use App\Models\TruckingContType;
use App\Models\TruckingCostLine;
use App\Models\TruckingCustomer;
use App\Models\TruckingLocation;
use App\Models\TruckingPayment;
use App\Models\TruckingRevenueLine;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use App\Models\TruckingWarehouse;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;

/**
 * Import luồng kế toán thực tế từ dev/mbf.xlsx (1 sheet = 1 tháng) vào Lô hàng v2.
 * Header ở DÒNG 8, data từ dòng 9. Map cột:
 *   B NHÀ MÁY → kho (nhà máy, chuẩn hóa QUẾ VÕ→QV / TIÊN SƠN→TS / THĂNG LONG→TL)
 *   M POL     → from_loc (Nơi lấy = cảng)      H NƠI HẠ → to_loc
 *   C booking · D Nhập/Xuất · E qty · F loại cont · G cắt máng
 *   I+N giờ xe đến · O cont đến (cont_no) · P cont ra (ghi chú) · Q+S giờ xe ra
 *   R thông tin xe (tách biển số → bks_vao=bks_ra) · T ghi chú · K cổng (ghi chú)
 * Tạo danh mục còn THIẾU (location cảng/nơi hạ, loại cont). Khách hàng = Canon.
 */
class ImportMbf extends Command
{
    protected $signature = 'trucking:import-mbf {sheet=JAN : Tên sheet/tháng} {--clear : Xóa TOÀN BỘ lô hàng trước khi import} {--limit=0 : Giới hạn số dòng (test)}';
    protected $description = 'Import lô hàng 1 tháng từ dev/mbf.xlsx (luồng kế toán thực tế)';

    public function handle(): int
    {
        $path = base_path('dev/mbf.xlsx');
        if (! is_file($path)) { $this->error("Không thấy file: $path"); return self::FAILURE; }
        $sheet = (string) $this->argument('sheet');

        if ($this->option('clear')) $this->clearShipments();

        $reader = IOFactory::createReaderForFile($path);
        $reader->setReadDataOnly(true);
        $ss = $reader->load($path);
        $sh = $ss->getSheetByName($sheet);
        if (! $sh) { $this->error("Không thấy sheet [$sheet]. Có: " . implode(', ', $ss->getSheetNames())); return self::FAILURE; }

        $cust = TruckingCustomer::firstOrCreate(['name' => 'Canon']);

        // map nhà máy (text VN) → mã warehouse có sẵn ở cai-dat
        $nhaMay = function (string $v): string {
            $v = trim(preg_replace('/^\s*CANON\s+/iu', '', mb_strtoupper($v)));
            return [
                'QUẾ VÕ' => 'QV', 'TIÊN SƠN' => 'TS', 'THĂNG LONG' => 'TL',
            ][$v] ?? $v;
        };
        // cache danh mục để tạo-nếu-thiếu 1 lần
        $whByCode = TruckingWarehouse::get()->keyBy(fn ($w) => mb_strtoupper((string) ($w->code ?: $w->name)));
        $ensureWh = function (string $code) use (&$whByCode) {
            $k = mb_strtoupper($code);
            if (! isset($whByCode[$k])) $whByCode[$k] = TruckingWarehouse::firstOrCreate(['code' => $code], ['name' => $code]);
            return $code;
        };
        $locByKey = TruckingLocation::get()->keyBy(fn ($l) => mb_strtoupper((string) ($l->code ?: $l->name)));
        $ensureLoc = function (string $val) use (&$locByKey) {
            $val = trim($val);
            if ($val === '') return '';
            $k = mb_strtoupper($val);
            if (! isset($locByKey[$k])) $locByKey[$k] = TruckingLocation::firstOrCreate(['code' => $val], ['name' => $val]);
            return $val;
        };
        $ensureCont = function (string $val) {
            $val = trim($val);
            if ($val !== '') TruckingContType::firstOrCreate(['name' => $val]);
            return $val;
        };

        $toDt = function ($serial) {
            if (! is_numeric($serial) || (float) $serial <= 0) return null;
            try { return Carbon::instance(XlDate::excelToDateTimeObject((float) $serial)); } catch (\Throwable) { return null; }
        };
        // ghép NGÀY (serial) + GIỜ (fraction) → datetime
        $dtAt = function ($dateSerial, $frac) use ($toDt) {
            if (! is_numeric($dateSerial) || (float) $dateSerial <= 0) return null;
            $s = (float) $dateSerial; $s = floor($s) + (is_numeric($frac) ? (float) $frac - floor((float) $frac) : 0);
            return $toDt($s);
        };
        $plate = function (string $r): string {
            if (preg_match('/(\d{2}[A-Z]{1,2}\d?)[-\s]?(\d{3}\.?\d{2,3})/u', $r, $m)) {
                return $m[1] . '-' . $m[2];
            }
            return '';
        };

        $svc = app(\App\Services\TruckingV2Service::class);
        $hi = $sh->getHighestDataRow();
        $limit = (int) $this->option('limit');
        $made = 0; $skip = 0;
        $cell = fn ($c, $r) => trim((string) $sh->getCell($c . $r)->getValue());

        $this->info("Đọc sheet [$sheet] (data dòng 9 → $hi)…");
        $bar = $this->output->createProgressBar();
        for ($r = 9; $r <= $hi; $r++) {
            $booking = $cell('C', $r);
            if ($booking === '') { $skip++; continue; }   // dòng trống/ghi chú

            $b = $cell('B', $r); $io = mb_strtoupper($cell('D', $r));
            $contType = $ensureCont($cell('F', $r));
            $khoCode  = $b !== '' ? $ensureWh($nhaMay($b)) : '';
            $fromCode = $ensureLoc($cell('M', $r));         // POL = cảng (nơi lấy)
            $toRaw    = $cell('H', $r);
            $toCode   = preg_match('/^\d|cont|chờ|etd|xác nhận/iu', $toRaw) ? $toRaw : $ensureLoc($toRaw);
            $bks      = $plate($cell('R', $r));

            $gioDen = $dtAt($cell('I', $r), $sh->getCell('N' . $r)->getValue());
            $gioRa  = $dtAt($cell('S', $r) ?: $cell('I', $r), $sh->getCell('Q' . $r)->getValue());
            $cutOff = $toDt($sh->getCell('G' . $r)->getValue());

            $contNoIn = $cell('O', $r); $contNoOut = $cell('P', $r); $cong = $cell('K', $r);
            $ngayDen = $toDt($cell('I', $r)); $ngayRa = $toDt($cell('S', $r));   // cont_den/cont_ra là cột NGÀY
            $note = $cell('T', $r);
            $extra = array_filter([
                $contNoOut && $contNoOut !== $contNoIn ? "Cont ra: $contNoOut" : null,
                $cong !== '' ? "Cổng: $cong" : null,
                str_contains($io, 'RU') ? 'Tuyến RU' : null,
            ]);

            $s = TruckingShipment::create([
                'sheet'       => 'icd',
                'customer_id' => $cust->id,
                'booking'     => $booking,
                'inv'         => $cell('L', $r),
                'io'          => str_contains($io, 'NHẬP') ? 'Nhập' : 'Xuất',
                'qty'         => ($q = $cell('E', $r)) !== '' ? $q : null,
                'cont_type'   => $contType,
                'cont_no'     => $contNoIn,                 // số container đến
                'kho'         => $khoCode,
                'from_loc'    => $fromCode,
                'to_loc'      => is_string($toCode) ? $toCode : $toRaw,
                'bks_vao'     => $bks,
                'bks_ra'      => $bks,
                'gio_xe_den'  => $gioDen,
                'gio_xe_ra'   => $gioRa,
                'cont_den'    => $ngayDen,                  // ngày cont đến (cột date)
                'cont_ra'     => $ngayRa,                   // ngày cont ra (cột date)
                'cut_off'     => $cutOff,
                'ra_mode'     => 'self',
                'vat_rate'    => '0',
                'ghi_chu'     => trim($note . ($extra ? ($note ? ' · ' : '') . implode(' · ', $extra) : '')),
            ]);
            $svc->recomputeShipmentDerived($s);
            $made++;
            if ($limit && $made >= $limit) break;
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        $this->info("Xong: tạo $made lô (bỏ $skip dòng trống) từ [$sheet].");
        $this->line('Khách hàng=Canon · nhà máy→kho(QV/TS/TL) · POL→nơi lấy(cảng) · nơi hạ→to_loc. Danh mục thiếu đã được tạo.');
        return self::SUCCESS;
    }

    private function clearShipments(): void
    {
        $n = TruckingShipment::count();
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        TruckingPayment::query()->delete();
        TruckingRevenueLine::query()->delete();
        TruckingCostLine::query()->delete();
        TruckingShipmentWarehouse::query()->delete();
        if (class_exists(\App\Models\TruckingShipmentSpend::class)) \App\Models\TruckingShipmentSpend::query()->delete();
        TruckingShipment::query()->delete();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        $this->warn("Đã xóa TOÀN BỘ $n lô hàng (+ chi phí/doanh thu/thanh toán/kho theo lô).");
    }
}
