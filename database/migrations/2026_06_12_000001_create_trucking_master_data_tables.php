<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Trucking v2 — master data (danh mục).
 *
 * Thực thể giàu thuộc tính (khách hàng, xe, bảng giá) có bảng riêng.
 * Các list tag nhẹ (địa điểm, payer, tên khoản…) gộp vào `trucking_catalogs`
 * để gợi ý autocomplete + đơn giá mặc định — không tạo 8 bảng gần giống nhau.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Khách hàng — MST, liên hệ, hạn TT, địa chỉ xuất hóa đơn…
        Schema::create('trucking_customers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('short_name')->nullable();
            $table->string('tax_code')->nullable();
            $table->string('phone')->nullable();
            $table->string('contact')->nullable();
            $table->string('email')->nullable();
            $table->unsignedSmallInteger('term_days')->nullable(); // hạn thanh toán mặc định (ngày)
            $table->text('address')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // Đội xe — mỗi biển số là Xe MBF hay Xe ngoài
        Schema::create('trucking_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('plate')->unique();
            $table->string('type', 16)->default('MBF'); // 'MBF' | 'Ngoài'
            $table->timestamps();
        });

        // Lookup gộp: locations | payers | cost_items | choho_items | revenue_items
        //            | drivers | cont_types | warehouses
        Schema::create('trucking_catalogs', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32)->index();
            $table->string('value');
            $table->string('code', 32)->nullable();          // ký hiệu viết tắt (địa điểm)
            $table->decimal('default_price', 15, 2)->nullable(); // đơn giá mặc định (khoản có giá)
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['type', 'value']);
        });

        // Cấu hình key/value: vat_default_hph, vat_default_icd, free_time_hours…
        Schema::create('trucking_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        // Bảng giá đã gửi — riêng từng khách hàng
        Schema::create('trucking_price_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('trucking_customers')->cascadeOnDelete();
            $table->string('loc')->nullable();             // địa điểm hạ (nhóm)
            $table->string('conn', 16)->default('Connect'); // Connect | Disconnect
            $table->string('kind')->nullable();            // KIND / nhóm
            $table->string('from')->nullable();
            $table->string('to1')->nullable();
            $table->string('to2')->nullable();
            $table->string('to3')->nullable();
            $table->string('to4')->nullable();
            $table->string('distance', 32)->nullable();    // KM
            $table->decimal('trans_fee', 15, 2)->nullable();
            $table->decimal('fuel_fee', 15, 2)->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_price_rows');
        Schema::dropIfExists('trucking_settings');
        Schema::dropIfExists('trucking_catalogs');
        Schema::dropIfExists('trucking_vehicles');
        Schema::dropIfExists('trucking_customers');
    }
};
