<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bổ sung vehicle_id cho dữ liệu CŨ:
 * 1) trucking_route_pays: gán vehicle_id nếu null nhưng bks khớp xe hiện tại.
 * 2) trucking_payroll_periods: thêm vehicleId vào mỗi line JSON (khớp bks → vehicle).
 * Giúp tính lại lịch sử + báo cáo theo vehicle_id chính xác.
 */
return new class extends Migration
{
    public function up(): void
    {
        $norm = fn ($p) => preg_replace('/[\s\-.,]/u', '', mb_strtoupper(trim((string) $p)));
        $vehicles = DB::table('trucking_vehicles')->get(['id', 'plate']);
        $byNorm = []; $byPlate = [];
        foreach ($vehicles as $v) {
            $byNorm[$norm($v->plate)] = (int) $v->id;
            $byPlate[$v->plate] = (int) $v->id;
        }

        // 1) route_pays: gán vehicle_id nếu null/0
        $fixedPays = 0;
        if (DB::getSchemaBuilder()->hasTable('trucking_route_pays')) {
            foreach (DB::table('trucking_route_pays')->where(function ($q) { $q->whereNull('vehicle_id')->orWhere('vehicle_id', 0); })
                ->whereNotNull('bks')->where('bks', '!=', '')->get(['id', 'bks']) as $rp) {
                $vid = $byPlate[$rp->bks] ?? $byNorm[$norm($rp->bks)] ?? null;
                if ($vid) { DB::table('trucking_route_pays')->where('id', $rp->id)->update(['vehicle_id' => $vid]); $fixedPays++; }
            }
        }

        // 2) payroll_periods: thêm vehicleId vào mỗi line JSON
        $fixedPeriods = 0;
        if (DB::getSchemaBuilder()->hasTable('trucking_payroll_periods')) {
            foreach (DB::table('trucking_payroll_periods')->get(['id', 'lines']) as $p) {
                $lines = is_string($p->lines) ? json_decode($p->lines, true) : $p->lines;
                if (! is_array($lines) || ! $lines) continue;
                $changed = false;
                foreach ($lines as &$line) {
                    if (isset($line['vehicleId']) && $line['vehicleId']) continue;
                    $bks = trim((string) ($line['bks'] ?? ''));
                    $vid = $byPlate[$bks] ?? $byNorm[$norm($bks)] ?? null;
                    if ($vid) { $line['vehicleId'] = $vid; $changed = true; }
                }
                unset($line);
                if ($changed) {
                    DB::table('trucking_payroll_periods')->where('id', $p->id)->update(['lines' => json_encode($lines, JSON_UNESCAPED_UNICODE)]);
                    $fixedPeriods++;
                }
            }
        }

        Log::info("backfill_vehicle_id: route_pays={$fixedPays} payroll_periods={$fixedPeriods}");
    }

    public function down(): void
    {
        // vehicleId là bổ sung — không cần rollback (giữ nguyên, không gây hại).
    }
};
