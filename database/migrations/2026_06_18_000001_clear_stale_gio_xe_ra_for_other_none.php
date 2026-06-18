<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Dọn dữ liệu cũ: lô có ra_mode = 'other' (cont khác kéo ra) hoặc 'none' (xe ra không kéo cont)
 * thì CONT này KHÔNG tự ra → gio_xe_ra (giờ ra của cont) + bks_ra phải TRỐNG. Trước khi có guard,
 * các lô này còn dính gio_xe_ra/bks_ra cũ → list báo "Đã ra" trong khi popup hiện "Để trống" (mâu thuẫn).
 *
 * - ra_mode='none': giờ đang ở gio_xe_ra thực ra là giờ XE rời đi → chuyển sang gio_xe_ra_xe nếu cột đó
 *   đang trống (giữ lại mốc giờ xe), rồi mới xóa gio_xe_ra + bks_ra.
 * - ra_mode='other': giờ ra/BKS thật nằm ở cont ra_other_id → chỉ xóa gio_xe_ra + bks_ra của lô này.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1) none: bảo toàn giờ xe ra (đầu kéo) sang cột riêng nếu chưa có
        //    (cột datetime chỉ NULL hoặc hợp lệ — KHÔNG so sánh với '' để tránh strict-mode error)
        DB::table('trucking_shipments')
            ->where('ra_mode', 'none')
            ->whereNotNull('gio_xe_ra')
            ->whereNull('gio_xe_ra_xe')
            ->update(['gio_xe_ra_xe' => DB::raw('gio_xe_ra')]);

        // 2) other + none: cont không tự ra → xóa giờ ra (của cont) + biển số ra
        DB::table('trucking_shipments')
            ->whereIn('ra_mode', ['other', 'none'])
            ->update(['gio_xe_ra' => null, 'bks_ra' => null]);
    }

    public function down(): void
    {
        // Không khôi phục (dữ liệu đã được chuẩn hóa theo quy tắc đúng).
    }
};
