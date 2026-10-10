<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Khôi phục xe MBF bị chuyển nhầm sang "Xe ngoài" ở Cài đặt → Biển số xe (1 cú bấm + Lưu, không hỏi lại):
 * xe biến khỏi Quản lý xe dù vẫn còn phiếu chi / khấu hao, và bị gỡ GPS.
 *
 * Phiếu chi & khấu hao chỉ tạo được cho xe MBF (Quản lý xe, /yeu-cau-chi đều lọc mbfFleet) → xe 'vehicle'
 * KHÔNG phải MBF mà có phiếu chi hoặc khấu hao chắc chắn từng là MBF → đưa về MBF.
 * GPS chỉ khôi phục được cho xe đã biết mã cũ (theo bản sao lưu trước sự cố), và chỉ khi mã đó chưa gán xe khác.
 */
return new class extends Migration
{
    /** biển số => mã GPS trước khi bị chuyển nhầm */
    private const GPS_BEFORE = ['29C-18195' => 'dvbk:90000935'];

    public function up(): void
    {
        $ids = DB::table('trucking_vehicles as v')
            ->where('v.kind', 'vehicle')->where('v.type', '<>', 'MBF')
            ->where(function ($w) {
                $w->whereExists(fn ($q) => $q->from('trucking_vehicle_costs as c')->whereColumn('c.vehicle_id', 'v.id'))
                  ->orWhereExists(fn ($q) => $q->from('trucking_vehicle_depreciations as d')->whereColumn('d.vehicle_id', 'v.id'));
            })
            ->pluck('v.id');
        if ($ids->isNotEmpty()) DB::table('trucking_vehicles')->whereIn('id', $ids)->update(['type' => 'MBF', 'updated_at' => now()]);

        foreach (self::GPS_BEFORE as $plate => $ref) {
            if (DB::table('trucking_vehicles')->where('gps_ref', $ref)->exists()) continue;   // mã đã gán xe khác → không giành
            DB::table('trucking_vehicles')->where('plate', $plate)->where('kind', 'vehicle')->where('type', 'MBF')
                ->whereNull('gps_ref')->update(['gps_ref' => $ref]);
        }
    }

    /** Không đảo lại: hạ xe xuống "Xe ngoài" chính là lỗi cần sửa. */
    public function down(): void
    {
    }
};
