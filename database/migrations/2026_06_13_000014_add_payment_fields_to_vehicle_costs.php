<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Thông tin DUYỆT THANH TOÁN (kế toán điền khi duyệt): ngày TT, hình thức, số chứng từ, ghi chú. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->date('paid_date')->nullable()->after('paid');
            $table->string('paid_method')->nullable()->after('paid_date');   // Chuyển khoản | Tiền mặt | Khác
            $table->string('paid_ref')->nullable()->after('paid_method');    // số UNC / chứng từ
            $table->string('paid_note')->nullable()->after('paid_ref');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_vehicle_costs', function (Blueprint $table) {
            $table->dropColumn(['paid_date', 'paid_method', 'paid_ref', 'paid_note']);
        });
    }
};
