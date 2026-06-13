<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Lương nhân sự" theo tuyến: đánh dấu khoản nào của phí tuyến tính vào LƯƠNG LÁI XE
 * (mặc định Trợ cấp + Lương). Lưu danh sách key khoản (JSON) trên từng tuyến, và chụp
 * lại (snapshot) trên từng dòng phí xe để tổng hợp lương nhân sự cho lái xe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->json('salary_parts')->nullable()->after('luong');
        });
        Schema::table('trucking_trip_cost_lines', function (Blueprint $table) {
            $table->json('salary_parts')->nullable()->after('luong');
        });

        // backfill: tuyến cũ mặc định Trợ cấp + Lương là lương nhân sự
        DB::table('trucking_route_fees')->update(['salary_parts' => json_encode(['troCap', 'luong'])]);
    }

    public function down(): void
    {
        Schema::table('trucking_route_fees', fn (Blueprint $t) => $t->dropColumn('salary_parts'));
        Schema::table('trucking_trip_cost_lines', fn (Blueprint $t) => $t->dropColumn('salary_parts'));
    }
};
