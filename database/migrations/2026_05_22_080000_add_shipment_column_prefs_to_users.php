<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $t) {
            // Mảng các key cột mà user tự chọn ẩn (preference cá nhân, không phải quyền)
            // vd: ["payment_amount", "agent_fee_vnd"]
            $t->json('shipment_column_prefs')->nullable()->after('column_permissions');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->dropColumn('shipment_column_prefs');
        });
    }
};
