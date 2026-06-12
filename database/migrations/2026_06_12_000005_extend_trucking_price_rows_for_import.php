<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng giá khách hàng — chuẩn cho import từ Excel (sheet "Import Data"):
 *  - location_id: FK tới trucking_locations (Điểm Hạ link theo ký hiệu/code).
 *  - tách phí 40FT / 20FT (transport + fuel) thay cho phí đơn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_price_rows', function (Blueprint $table) {
            $table->foreignId('location_id')->nullable()->after('customer_id')
                  ->constrained('trucking_locations')->nullOnDelete();
            $table->decimal('trans_fee_40', 15, 2)->nullable()->after('distance');
            $table->decimal('trans_fee_20', 15, 2)->nullable()->after('trans_fee_40');
            $table->decimal('fuel_fee_40', 15, 2)->nullable()->after('trans_fee_20');
            $table->decimal('fuel_fee_20', 15, 2)->nullable()->after('fuel_fee_40');
            $table->dropColumn(['trans_fee', 'fuel_fee']);
        });
    }

    public function down(): void
    {
        Schema::table('trucking_price_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['trans_fee_40', 'trans_fee_20', 'fuel_fee_40', 'fuel_fee_20']);
            $table->decimal('trans_fee', 15, 2)->nullable();
            $table->decimal('fuel_fee', 15, 2)->nullable();
        });
    }
};
