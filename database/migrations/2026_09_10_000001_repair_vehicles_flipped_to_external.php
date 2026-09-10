<?php

use App\Models\TruckingShipment;
use App\Models\TruckingVehicle;
use App\Services\TruckingV2Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * SỬA DỮ LIỆU do lỗi "thêm nhanh biển số" (đã vá ở reconcileVehicles).
 *
 * Gõ biển vào ô BKS của lô hàng mặc định là "Xe ngoài"; biển gõ lệch dạng (29C18195 / 29C-181.95)
 * chuẩn hóa ra TRÙNG một xe MBF sẵn có nên xe đó bị update type='Ngoài' → biến mất khỏi trang Quản
 * lý xe (lọc type='MBF') và lần lưu Cài đặt kế tiếp xóa luôn gps_ref → trông như mất sạch dữ liệu.
 *
 * 1) Trả về type='MBF' cho xe đang là "Ngoài" nhưng có dữ liệu CHỈ xe nội bộ mới có (khấu hao /
 *    lịch sử sử dụng / tài liệu). Xe ngoài (thuê) không bao giờ có khấu hao trong sổ của mình.
 *    KHÔNG đụng xe "Ngoài" đúng nghĩa (không có các dữ liệu đó).
 * 2) Nối lại vehicle_id cho lô có BKS gõ lệch dạng — trước đây chỉ so chuỗi chính xác nên NULL,
 *    khiến lô biến mất khỏi báo cáo/lộ trình theo xe.
 * Không xóa gì; chạy lại nhiều lần vẫn an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1) Xe nội bộ bị đổi nhầm thành "Xe ngoài" ----
        $fixed = [];
        foreach (TruckingVehicle::where('kind', 'vehicle')->where('type', 'Ngoài')->get() as $v) {
            $internal = $v->vehicleDepreciations()->count() + $v->vehicleUsages()->count()
                + DB::table('trucking_attachments')->where('owner_type', TruckingVehicle::class)->where('owner_id', $v->id)->count();
            if ($internal === 0) continue;   // đúng là xe ngoài → giữ nguyên
            $v->type = 'MBF';
            // GPS bị xóa theo lúc đổi type: gán lại thiết bị có biển TRÙNG DẠNG CHUẨN, nếu thiết bị đang rảnh.
            if (! $v->gps_ref) {
                try {
                    $norm = fn ($s) => preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $s)) ?? '';
                    $hit = collect(app(\App\Services\Gps\GpsTrackingService::class)->vehicleOptions())
                        ->first(fn ($o) => $norm($o['plate'] ?? '') === $norm($v->plate));
                    if ($hit && ! TruckingVehicle::where('gps_ref', $hit['ref'])->exists()) $v->gps_ref = $hit['ref'];
                } catch (\Throwable) {
                    // GPS không kết nối được lúc deploy → bỏ qua, gán tay ở Cài đặt → Biển số xe.
                }
            }
            $v->save();
            $fixed[] = $v->plate;
        }
        if ($fixed) echo "  [repair] Trả về Xe MBF: " . implode(', ', $fixed) . "\n";

        // ---- 2) Lô mất liên kết xe do BKS gõ lệch dạng ----
        $svc = app(TruckingV2Service::class);
        $n = 0;
        TruckingShipment::whereNull('vehicle_id')->whereNotNull('bks_vao')->where('bks_vao', '!=', '')
            ->chunkById(200, function ($rows) use ($svc, &$n) {
                foreach ($rows as $s) {
                    $svc->recomputeShipmentDerived($s, ['bksVao']);
                    if ($s->fresh()->vehicle_id) $n++;
                }
            });
        if ($n) echo "  [repair] Nối lại liên kết xe cho {$n} lô hàng\n";
    }

    public function down(): void
    {
        // Sửa dữ liệu sai — không khôi phục trạng thái lỗi.
    }
};
