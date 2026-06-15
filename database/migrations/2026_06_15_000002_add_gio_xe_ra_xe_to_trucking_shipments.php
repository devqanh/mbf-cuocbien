<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tách giờ xe ra của XE (đầu kéo) ra cột riêng `gio_xe_ra_xe`.
 *  - `gio_xe_ra`     = LUÔN là giờ ra của CONT (lô tự ra / cont khác ra).
 *  - `gio_xe_ra_xe`  = giờ XE ra khi "không kéo công ra" (ra_mode='none') — cont KHÔNG ra,
 *                      chỉ ghi mốc xe rời để sau tính phí các hạng mục khác.
 * Tách cột để query/tham chiếu rạch ròi, không lẫn vào free-time của cont.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dateTime('gio_xe_ra_xe')->nullable()->after('gio_xe_ra');
        });
    }

    public function down(): void
    {
        Schema::table('trucking_shipments', function (Blueprint $table) {
            $table->dropColumn('gio_xe_ra_xe');
        });
    }
};
