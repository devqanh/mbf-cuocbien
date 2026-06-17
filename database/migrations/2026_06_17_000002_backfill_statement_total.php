<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Đồng bộ cột trucking_statements.total = TỔNG phai_thu các dòng.
 * Sửa bảng kê cũ bị total=0 (tạo trước khi khớp giá đúng) trong khi dòng đã có tiền.
 * Idempotent: chạy lại = set lại đúng tổng dòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            UPDATE trucking_statements s
            SET s.total = COALESCE((
                SELECT SUM(l.phai_thu) FROM trucking_statement_lines l WHERE l.statement_id = s.id
            ), 0)
        ');
    }

    public function down(): void
    {
        // no-op
    }
};
