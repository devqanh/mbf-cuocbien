<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            // 1) Rename cột cũ — báo cáo phát sinh tăng
            $t->renameColumn('report_close_date', 'report_close_date_increase');
        });

        Schema::table('shipments', function (Blueprint $t) {
            // 2) Thêm cột mới ngay cạnh — báo cáo phát sinh giảm
            $t->date('report_close_date_decrease')->nullable()->after('report_close_date_increase');
        });

        // 3) Xoá snapshot cũ vì layout cột đã đổi
        DB::table('sheet_snapshots')->where('key', 'like', 'shipments_grid%')->delete();
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            $t->dropColumn('report_close_date_decrease');
            $t->renameColumn('report_close_date_increase', 'report_close_date');
        });
    }
};
