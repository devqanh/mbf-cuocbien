<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BẢNG GIÁ THEO LOẠI CONT: mỗi dòng giá lưu `prices` JSON {loại cont => giá TỔNG (cước + dầu)} thay cho
 * 4 cột cứng cước/dầu × 40/20 (không còn tách cước và dầu).
 *
 * Backfill: 20FT = cước20 + dầu20 · 40FT = cước40 + dầu40. "20FT"/"40FT" là CỘT CHUNG áp cho mọi loại
 * cont cùng cỡ chưa có cột riêng → lô hiện có định giá ra đúng số tổng như trước.
 * 4 cột cũ GIỮ NGUYÊN dữ liệu (không xóa), chỉ không còn được ghi/đọc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_price_rows', function (Blueprint $table) {
            $table->json('prices')->nullable()->after('distance');
        });

        DB::table('trucking_price_rows')->whereNull('prices')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $r) {
                $p = [];
                $t20 = (float) $r->trans_fee_20 + (float) $r->fuel_fee_20;
                $t40 = (float) $r->trans_fee_40 + (float) $r->fuel_fee_40;
                if ($t20 > 0) $p['20FT'] = (int) round($t20);
                if ($t40 > 0) $p['40FT'] = (int) round($t40);
                DB::table('trucking_price_rows')->where('id', $r->id)
                    ->update(['prices' => $p ? json_encode($p) : null]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trucking_price_rows', function (Blueprint $table) {
            $table->dropColumn('prices');
        });
    }
};
