<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Bổ sung driverId cho dữ liệu kỳ lương CŨ (payroll_periods lines JSON).
 * Khớp driver text → trucking_drivers.id (lowercase trim).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('trucking_payroll_periods')) return;
        $drivers = DB::table('trucking_drivers')->get(['id', 'name']);
        $byName = [];
        foreach ($drivers as $d) { $k = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $d->name)) ?? ''); if ($k !== '') $byName[$k] = (int) $d->id; }

        $fixed = 0;
        foreach (DB::table('trucking_payroll_periods')->get(['id', 'lines']) as $p) {
            $lines = is_string($p->lines) ? json_decode($p->lines, true) : $p->lines;
            if (! is_array($lines) || ! $lines) continue;
            $changed = false;
            foreach ($lines as &$line) {
                if (isset($line['driverId']) && $line['driverId']) continue;
                $name = trim((string) ($line['driver'] ?? ''));
                if ($name === '') continue;
                $k = mb_strtolower(preg_replace('/\s+/u', ' ', $name) ?? '');
                $did = $byName[$k] ?? null;
                if ($did) { $line['driverId'] = $did; $changed = true; }
            }
            unset($line);
            if ($changed) {
                DB::table('trucking_payroll_periods')->where('id', $p->id)->update(['lines' => json_encode($lines, JSON_UNESCAPED_UNICODE)]);
                $fixed++;
            }
        }
        Log::info("backfill_driver_id_payroll: periods_fixed={$fixed}");
    }

    public function down(): void {}
};
