<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * BẢNG GIÁ THEO KHOẢNG NGÀY (price books): mỗi khách có nhiều bảng giá, mỗi bảng có [từ ngày, đến ngày].
 *  - trucking_price_books: container 1 phiên bản giá (customer + khoảng ngày + nhãn).
 *  - trucking_price_rows.price_book_id: dòng giá thuộc về bảng giá nào.
 * Backfill: gom toàn bộ dòng giá hiện có của mỗi khách vào 1 bảng "Mặc định (mọi ngày)" (from/to = null
 * → phủ mọi ngày) → bảng kê hiện tại chạy y nguyên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trucking_price_books', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('trucking_customers')->cascadeOnDelete();
            $table->string('label')->nullable();             // nhãn tùy chọn (vd "Giá Q3/2026")
            $table->date('period_from')->nullable();          // null = không giới hạn đầu
            $table->date('period_to')->nullable();            // null = không giới hạn cuối
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
            $table->index(['customer_id', 'period_from']);
        });

        Schema::table('trucking_price_rows', function (Blueprint $table) {
            $table->foreignId('price_book_id')->nullable()->after('customer_id')
                  ->constrained('trucking_price_books')->cascadeOnDelete();
        });

        // Backfill: mỗi khách có dòng giá → 1 bảng giá mở; gán price_book_id.
        $custIds = DB::table('trucking_price_rows')->whereNotNull('customer_id')
            ->distinct()->pluck('customer_id');
        foreach ($custIds as $cid) {
            $bookId = DB::table('trucking_price_books')->insertGetId([
                'customer_id' => $cid,
                'label'       => 'Mặc định (mọi ngày)',
                'period_from' => null,
                'period_to'   => null,
                'sort'        => 0,
                'created_at'  => now(),
                'updated_at'  => now(),
            ]);
            DB::table('trucking_price_rows')->where('customer_id', $cid)->update(['price_book_id' => $bookId]);
        }
    }

    public function down(): void
    {
        Schema::table('trucking_price_rows', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_book_id');
        });
        Schema::dropIfExists('trucking_price_books');
    }
};
