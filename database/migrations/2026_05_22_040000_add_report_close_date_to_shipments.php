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
            $t->date('report_close_date')->nullable()->after('supplier_due_date');  // Ngày chốt báo cáo
        });

        // Xoá snapshot cũ vì layout cột đã đổi
        DB::table('sheet_snapshots')->where('key', 'like', 'shipments_grid%')->delete();
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $t) {
            $t->dropColumn('report_close_date');
        });
    }
};
