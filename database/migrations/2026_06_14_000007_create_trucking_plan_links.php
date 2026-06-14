<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link kế hoạch công khai cho lái xe cập nhật giờ xe đến/ra + ghi chú/ảnh.
 * Token ngẫu nhiên (bí mật) vì link cho phép GHI mà không cần đăng nhập.
 * Phạm vi lô = các lô có "giờ đến dự kiến" trong [from_date, to_date].
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_plan_links', function (Blueprint $table) {
            $table->id();
            $table->string('token', 40)->unique();
            $table->string('title')->nullable();
            $table->date('from_date');
            $table->date('to_date');
            $table->boolean('active')->default(true);
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        // Ghi chú của lái xe (tách khỏi ghi chú nội bộ để không ghi đè lẫn nhau)
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->text('driver_note')->nullable()->after('csht_note');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trucking_plan_links');
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn('driver_note');
        });
    }
};
