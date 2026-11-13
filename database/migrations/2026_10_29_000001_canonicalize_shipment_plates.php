<?php

use App\Support\MigrationBackup;
use App\Support\PlateNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Đưa BKS vào / ra của Lô hàng (và bks chi lái theo ngày) về CÙNG 1 format với danh mục xe:
 * "15C 15877" / "15c-158.77" / "15C15877" → "15C-15877" (PlateNormalizer::canonical — model
 * TruckingShipment cũng tự chuẩn hóa khi lưu từ nay).
 *
 * Ghi thẳng bằng query (không qua model) nên không đẻ nhật ký thao tác cho hàng trăm lô.
 * route_pays unique (work_date, bks): nếu dạng chuẩn đã có dòng cùng ngày thì GIỮ NGUYÊN dòng đó
 * (không gộp tiền tự động) và ghi log để xử lý tay. Idempotent: chạy lại = no-op.
 * Giá trị cũ lưu ở MigrationBackup trước khi sửa → `migrate:rollback` khôi phục đúng như trước.
 */
return new class extends Migration
{
    private const BACKUP = '2026_10_29_canonicalize_shipment_plates';

    public function up(): void
    {
        // Gom thay đổi trước, lưu bản cũ, rồi mới ghi.
        $ships = [];
        foreach (DB::table('trucking_shipments')->where(fn ($w) => $w->whereNotNull('bks_vao')->orWhereNotNull('bks_ra'))->get(['id', 'bks_vao', 'bks_ra']) as $s) {
            $new = [];
            foreach (['bks_vao', 'bks_ra'] as $col) {
                if ($s->$col !== null && ($c = PlateNormalizer::canonical($s->$col)) !== $s->$col) $new[$col] = $c;
            }
            if ($new) $ships[$s->id] = ['old' => ['bks_vao' => $s->bks_vao, 'bks_ra' => $s->bks_ra], 'new' => $new];
        }
        $pays = [];
        if (Schema::hasTable('trucking_route_pays')) {
            foreach (DB::table('trucking_route_pays')->whereNotNull('bks')->get(['id', 'work_date', 'bks']) as $rp) {
                $c = PlateNormalizer::canonical($rp->bks);
                if ($c === $rp->bks) continue;
                $taken = DB::table('trucking_route_pays')->where('work_date', $rp->work_date)->where('bks', $c)->where('id', '!=', $rp->id)->exists();
                if ($taken) { Log::warning("canonicalize_shipment_plates: route_pay #{$rp->id} ({$rp->bks}) trùng ngày với {$c} — giữ nguyên"); continue; }
                $pays[$rp->id] = ['old' => $rp->bks, 'new' => $c];
            }
        }
        MigrationBackup::save(self::BACKUP, [
            'shipments'  => array_map(fn ($x) => $x['old'], $ships),
            'route_pays' => array_map(fn ($x) => $x['old'], $pays),
        ]);

        DB::transaction(function () use ($ships, $pays) {
            foreach ($ships as $id => $x) DB::table('trucking_shipments')->where('id', $id)->update($x['new']);
            foreach ($pays as $id => $x) DB::table('trucking_route_pays')->where('id', $id)->update(['bks' => $x['new']]);
        });

        Log::info('canonicalize_shipment_plates: ' . json_encode(['shipments' => count($ships), 'route_pays' => count($pays)]));
    }

    public function down(): void
    {
        $b = MigrationBackup::load(self::BACKUP);
        if (! $b) return;   // không có bản sao lưu → không biết giá trị cũ, giữ nguyên
        DB::transaction(function () use ($b) {
            foreach ($b['shipments'] ?? [] as $id => $old) DB::table('trucking_shipments')->where('id', $id)->update($old);
            foreach ($b['route_pays'] ?? [] as $id => $old) DB::table('trucking_route_pays')->where('id', $id)->update(['bks' => $old]);
        });
    }
};
