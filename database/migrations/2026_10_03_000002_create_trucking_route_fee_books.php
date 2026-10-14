<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BẢNG PHÍ TUYẾN theo thời gian (như price book ở Bảng giá):
 *  - trucking_route_fee_books: 1 bộ phí tuyến đầy đủ, áp dụng từ `from_date` (null = bảng mặc định, mọi thời điểm).
 *  - trucking_route_fees.book_id: tuyến thuộc bảng nào.
 * Chuyến ngày D dùng bảng có from_date lớn nhất ≤ D. Tạo bảng mới = sao chép toàn bộ tuyến rồi sửa giá
 * → đổi giá cả bộ phí từ 1 ngày trong 1 thao tác, chuyến cũ vẫn theo bảng cũ.
 * Backfill: toàn bộ tuyến hiện có → 1 bảng "Mặc định (mọi thời điểm)" → Lộ trình chạy y nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Idempotent: DB kéo từ server có thể đã có bảng/cột này mà bảng migrations chưa ghi nhận.
        if (! Schema::hasTable('trucking_route_fee_books')) {
            Schema::create('trucking_route_fee_books', function (Blueprint $table) {
                $table->id();
                $table->string('label')->nullable();
                $table->date('from_date')->nullable();
                $table->unsignedInteger('sort')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasColumn('trucking_route_fees', 'book_id')) {
            Schema::table('trucking_route_fees', function (Blueprint $table) {
                $table->foreignId('book_id')->nullable()->after('id')
                      ->constrained('trucking_route_fee_books')->cascadeOnDelete();
            });
        }

        if (DB::table('trucking_route_fees')->whereNull('book_id')->exists()) {
            $id = DB::table('trucking_route_fee_books')->whereNull('from_date')->orderBy('id')->value('id')
                ?? DB::table('trucking_route_fee_books')->insertGetId([
                    'label' => 'Mặc định (mọi thời điểm)', 'from_date' => null, 'sort' => 0,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            DB::table('trucking_route_fees')->whereNull('book_id')->update(['book_id' => $id]);
        }
    }

    public function down(): void
    {
        Schema::table('trucking_route_fees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('book_id');
        });
        Schema::dropIfExists('trucking_route_fee_books');
    }
};
