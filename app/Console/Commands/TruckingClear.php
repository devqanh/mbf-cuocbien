<?php

namespace App\Console\Commands;

use App\Models\TruckingAttachment;
use App\Models\TruckingCostLine;
use App\Models\TruckingPayment;
use App\Models\TruckingRevenueLine;
use App\Models\TruckingShipment;
use App\Models\TruckingShipmentWarehouse;
use App\Models\TruckingStatement;
use App\Models\TruckingStatementLine;
use App\Models\TruckingStatementPayment;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingTripCostLine;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Xóa dữ liệu Trucking v2 để test lại.
 *  - MẶC ĐỊNH: chỉ NGHIỆP VỤ (LÔ HÀNG + BẢNG KÊ + PHÍ XE). GIỮ danh mục/cấu hình + bảng giá.
 *  - --all : XÓA SẠCH tất cả bảng trucking (lô hàng, bảng kê, phí xe, đội xe/quản lý xe,
 *            bảng giá, toàn bộ danh mục Cài đặt), CHỈ GIỮ "Cấu hình chung" (trucking_settings).
 * KHÔNG đụng bảng cũ `trucking_entries` (Luckysheet) trong cả 2 chế độ.
 */
class TruckingClear extends Command
{
    protected $signature = 'trucking:clear {--force : Xóa luôn, không hỏi xác nhận} {--all : Xóa SẠCH cả danh mục/bảng giá/đội xe (Cài đặt), chỉ giữ Cấu hình chung}';

    protected $description = 'Xóa dữ liệu Trucking v2. Mặc định: nghiệp vụ (lô/bảng kê/phí xe). --all: xóa sạch mọi thứ trừ Cấu hình chung';

    /** Bảng GIỮ LẠI khi --all: cấu hình chung + bảng cũ Luckysheet. */
    private const KEEP = ['trucking_settings', 'trucking_entries'];

    /** Xóa theo thứ tự con → cha để an toàn khóa ngoại. CHỈ bảng nghiệp vụ. */
    private array $order = [
        // Bảng kê
        TruckingStatementPayment::class,
        TruckingStatementLine::class,
        TruckingStatement::class,
        // Phí xe nội bộ (kỳ/snapshot)
        TruckingTripCostLine::class,
        TruckingTripCostBatch::class,
        // Lô hàng (con → cha)
        TruckingPayment::class,
        TruckingRevenueLine::class,
        TruckingCostLine::class,
        TruckingShipmentWarehouse::class,
        TruckingShipment::class,
    ];

    public function handle(): int
    {
        if ($this->option('all')) return $this->handleAll();

        // Tổng quan trước khi xóa
        $counts = [];
        $total = 0;
        foreach ($this->order as $m) {
            $c = $m::query()->count();
            $counts[class_basename($m)] = $c;
            $total += $c;
        }
        // Ảnh lô (đính qua bảng attachments polymorphic, owner = lô hàng) — xóa kèm để khỏi mồ côi
        $shipAtt = TruckingAttachment::where('owner_type', TruckingShipment::class)->count();
        $counts['Attachment (ảnh lô)'] = $shipAtt;
        $total += $shipAtt;

        if ($total === 0) {
            $this->info('Dữ liệu nghiệp vụ Trucking v2 đã trống — không có gì để xóa.');
            return self::SUCCESS;
        }

        $this->warn("Sắp xóa {$total} bản ghi NGHIỆP VỤ (lô hàng + bảng kê + phí xe):");
        foreach ($counts as $name => $c) {
            if ($c > 0) $this->line(sprintf('  %-26s %d', $name, $c));
        }
        $this->info('GIỮ NGUYÊN: danh mục & cấu hình (Cài đặt) + bảng giá.');

        if (! $this->option('force') && ! $this->confirm('Xóa các dữ liệu nghiệp vụ trên? Không thể hoàn tác.')) {
            $this->info('Đã hủy.');
            return self::SUCCESS;
        }

        foreach ($this->order as $m) {
            $m::query()->delete();
        }
        TruckingAttachment::where('owner_type', TruckingShipment::class)->delete();

        $this->info("Đã xóa {$total} bản ghi nghiệp vụ. Danh mục/cấu hình + bảng giá được giữ nguyên.");

        return self::SUCCESS;
    }

    /**
     * --all: xóa SẠCH mọi bảng trucking (nghiệp vụ + danh mục + bảng giá + đội xe),
     * CHỈ giữ "Cấu hình chung" (trucking_settings) + bảng cũ Luckysheet (trucking_entries).
     * Truncate với FK tắt → reset cả auto-increment.
     */
    private function handleAll(): int
    {
        $key = array_key_first((array) DB::select('SHOW TABLES')[0] ?? []);
        $tables = [];
        foreach (DB::select('SHOW TABLES') as $row) {
            $name = ((array) $row)[$key] ?? array_values((array) $row)[0];
            if (str_starts_with($name, 'trucking') && ! in_array($name, self::KEEP, true)) $tables[] = $name;
        }

        $total = 0;
        $counts = [];
        foreach ($tables as $t) { $c = DB::table($t)->count(); $counts[$t] = $c; $total += $c; }

        if ($total === 0) {
            $this->info('Tất cả bảng trucking (trừ Cấu hình chung) đã trống — không có gì để xóa.');
            return self::SUCCESS;
        }

        $this->warn("XÓA SẠCH {$total} bản ghi trên " . count($tables) . ' bảng trucking (lô hàng, bảng kê, phí xe, đội xe, bảng giá, danh mục):');
        foreach ($counts as $name => $c) { if ($c > 0) $this->line(sprintf('  %-32s %d', $name, $c)); }
        $this->info('CHỈ GIỮ: Cấu hình chung (trucking_settings)' . (in_array('trucking_entries', self::KEEP, true) ? ' + bảng cũ Luckysheet' : '') . '.');

        if (! $this->option('force') && ! $this->confirm('Xóa SẠCH toàn bộ dữ liệu trên? KHÔNG THỂ HOÀN TÁC.')) {
            $this->info('Đã hủy.');
            return self::SUCCESS;
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) DB::table($t)->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');

        $this->info("Đã xóa sạch {$total} bản ghi (" . count($tables) . ' bảng). Giữ nguyên Cấu hình chung.');

        return self::SUCCESS;
    }
}
