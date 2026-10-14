<?php

use App\Models\TruckingShipment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phí mở tờ khai + phí thanh lý là khoản THU KHÁCH qua Bảng kê, không phải chi phí của lô:
 *  - thêm `thanh_ly_fee` (phí thanh lý tờ khai, 1 số / lô) cạnh `declarations` [{no, fee}];
 *  - xóa các dòng chi phí "Phí mở tờ khai" trước đây tự sinh từ tờ khai (src=thanhLyFee) rồi tính lại
 *    tổng chi phí của các lô đó để báo cáo không còn tính khoản này là chi phí.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('trucking_shipments', 'thanh_ly_fee')) {
            Schema::table('trucking_shipments', function (Blueprint $t) {
                $t->unsignedBigInteger('thanh_ly_fee')->nullable()->after('declarations');   // phí thanh lý tờ khai (thu khách)
            });
        }

        $shipIds = DB::table('trucking_cost_lines')->where('src', 'thanhLyFee')->pluck('shipment_id')->filter()->unique()->values();
        if ($shipIds->isEmpty()) return;
        DB::table('trucking_cost_lines')->where('src', 'thanhLyFee')->delete();
        $svc = app(\App\Services\TruckingV2Service::class);
        foreach (TruckingShipment::whereIn('id', $shipIds)->get() as $s) $svc->recomputeShipmentDerived($s, ['cost']);
    }

    /** Dòng chi phí đã xóa không dựng lại (lấy lại từ bản sao lưu nếu cần); chỉ bỏ cột mới. */
    public function down(): void
    {
        if (Schema::hasColumn('trucking_shipments', 'thanh_ly_fee')) {
            Schema::table('trucking_shipments', fn (Blueprint $t) => $t->dropColumn('thanh_ly_fee'));
        }
    }
};
