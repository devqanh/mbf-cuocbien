<?php

use App\Models\TruckingVehicle;
use Illuminate\Database\Migrations\Migration;

/**
 * SỬA DỮ LIỆU: tài sản (kind='asset') bị ghi type='MBF' nên hiện ra như xe MBF ở Quản lý xe,
 * Yêu cầu chi và cảnh báo hạn chi phí xe.
 *
 * Nguyên nhân (đã vá ở reconcileVehicles): danh mục Biển số xe từng lấy cả tài sản. Khi lưu danh mục,
 * mã tài sản gõ có dấu chấm (vd "29RM-032.29") bị chuẩn hóa thành "29RM-03229"; tra loại theo mã mới
 * không thấy nên rơi về mặc định 'MBF'.
 *
 * Chỉ trả type về 'asset'. Không xóa gì; chạy lại nhiều lần vẫn an toàn.
 */
return new class extends Migration
{
    public function up(): void
    {
        $fixed = [];
        foreach (TruckingVehicle::where('kind', 'asset')
            ->where(fn ($q) => $q->where('type', '!=', 'asset')->orWhereNull('type'))->get() as $v) {
            $v->type = 'asset';
            $v->save();
            $fixed[] = $v->plate;
        }
        if ($fixed) echo "  [repair] Trả về Tài sản: " . implode(', ', $fixed) . "\n";
    }

    public function down(): void
    {
        // Sửa dữ liệu sai — không khôi phục trạng thái lỗi.
    }
};
