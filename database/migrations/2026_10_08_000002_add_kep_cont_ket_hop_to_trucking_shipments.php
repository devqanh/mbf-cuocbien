<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Lô hàng — 2 ô đánh dấu loại chuyến theo Quy chế lương 03/2026/QC-MBF (mục 2b — lương / phụ cấp tính mức xa nhất,
 * +300k phụ cấp, +100k/+50k lương chuyến):
 *  - kep_cont: KẸP CONT = kẹp 2 cont 20' đi đóng hàng (trước đây chỉ ghi ở ô ghi chú lô "KẸP CONT …").
 *  - ket_hop:  KẾT HỢP = cont nhập lấy ở HP trả nhà máy rồi dùng luôn sang nhà máy khác đóng hàng xuất
 *              (kế toán đang nhập Nơi lấy = "RU").
 * Đánh dấu sẵn dữ liệu cũ theo đúng 2 dấu hiệu kế toán đang dùng (ghi chú "kẹp cont" / Nơi lấy RU).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->boolean('kep_cont')->default(false)->after('cru');
            $table->boolean('ket_hop')->default(false)->after('kep_cont');
        });

        // Nơi lấy = địa điểm ký hiệu / tên "RU" → Kết hợp
        $ruNames = DB::table('trucking_locations')->where('code', 'RU')->orWhere('name', 'RU')->pluck('name')->push('RU')->unique()->all();
        DB::table('trucking_shipments')->whereIn('from_loc', $ruNames)->update(['ket_hop' => true]);
        // Ghi chú lô có "kẹp cont" (collation không phân biệt hoa thường / dấu) → Kẹp cont
        DB::table('trucking_shipments')->where(function ($w) {
            $w->where('info_note', 'like', '%kẹp cont%')->orWhere('ghi_chu', 'like', '%kẹp cont%');
        })->update(['kep_cont' => true]);
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn(['kep_cont', 'ket_hop']);
        });
    }
};
