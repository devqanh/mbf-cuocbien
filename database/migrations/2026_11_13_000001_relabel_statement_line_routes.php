<?php

use App\Models\TruckingShipment;
use App\Support\MigrationBackup;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Sửa TUYẾN đã chốt trong bảng kê (detail.route / detail.bargeRoute). Nhiều địa điểm dùng chung 1 ký hiệu
 * (48 cảng HPP, 5 điểm LHP…) nhưng lúc định giá ký hiệu được đổi ra tên địa điểm THÊM SAU CÙNG → mọi lô HPP
 * hiện "VAN VINH LIEN", LHP hiện "SITC8". Đổi lại từng đoạn theo tên ghi trên chính lô (relabelRoute) — chỉ
 * chữ hiển thị, không đụng số tiền. Ghi thẳng bằng query nên không đẻ nhật ký thao tác. Chạy lại = no-op.
 * detail cũ lưu ở MigrationBackup trước khi sửa → `migrate:rollback` khôi phục đúng như trước.
 */
return new class extends Migration
{
    private const BACKUP = '2026_11_13_relabel_statement_line_routes';

    public function up(): void
    {
        $svc = app(\App\Services\TruckingV2Service::class);
        $changes = [];   // id => ['old' => detail gốc (chuỗi), 'new' => detail mới]

        DB::table('trucking_statement_lines')->whereNotNull('shipment_id')->whereNotNull('detail')
            ->orderBy('id')->select(['id', 'shipment_id', 'detail'])
            ->chunkById(500, function ($lines) use ($svc, &$changes) {
                $ships = TruckingShipment::whereIn('id', $lines->pluck('shipment_id')->unique())
                    ->get(['id', 'from_loc', 'to_loc', 'barge_drop', 'kho'])->keyBy('id');
                foreach ($lines as $l) {
                    $s = $ships->get($l->shipment_id);
                    $d = json_decode((string) $l->detail, true);
                    if (! $s || ! is_array($d)) continue;
                    $new = $d;
                    if (trim((string) ($d['route'] ?? '')) !== '') $new['route'] = $svc->relabelRoute((string) $d['route'], $s);
                    if (trim((string) ($d['bargeRoute'] ?? '')) !== '') $new['bargeRoute'] = $svc->relabelRoute((string) $d['bargeRoute'], $s, 'to');
                    if ($new !== $d) $changes[$l->id] = ['old' => $l->detail, 'new' => json_encode($new)];
                }
            });

        MigrationBackup::save(self::BACKUP, array_map(fn ($x) => $x['old'], $changes));
        DB::transaction(function () use ($changes) {
            foreach ($changes as $id => $x) DB::table('trucking_statement_lines')->where('id', $id)->update(['detail' => $x['new']]);
        });

        Log::info('relabel_statement_line_routes: ' . count($changes) . ' dòng');
    }

    public function down(): void
    {
        $b = MigrationBackup::load(self::BACKUP);
        if (! $b) return;   // không có bản sao lưu → giữ nguyên
        DB::transaction(function () use ($b) {
            foreach ($b as $id => $old) DB::table('trucking_statement_lines')->where('id', $id)->update(['detail' => $old]);
        });
    }
};
