<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingExtStatement;
use App\Models\TruckingExtStatementLine;
use App\Models\TruckingExtStatementPayment;
use App\Models\TruckingShipment;
use App\Support\Hashid;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Bảng kê xe ngoài (phải trả nhà xe thuê) — mirror HandlesStatements.
 *
 * Mỗi bảng kê = 1 NHÀ XE. Chọn nhà xe + khoảng ngày Giờ xe đến → gom các lô của
 * nhà xe đó (ext_fee > 0) → snapshot dòng + lịch sử thanh toán. Công nợ = tổng
 * cước − đã trả. KHÔNG định giá phức tạp — cước lấy thẳng từ shipments.ext_fee.
 */
trait HandlesExtStatements
{
    /**
     * Ứng viên cho 1 bảng kê xe ngoài: lô của 1 NHÀ XE trong khoảng GIỜ XE ĐẾN, ext_fee > 0.
     * Đẩy lọc vào SQL theo index (ext_vendor, gio_xe_den) — không kéo toàn bộ lô.
     */
    public function extStatementCandidates(string $vendor, ?string $from, ?string $to): array
    {
        $v = trim($vendor);
        if ($v === '') return ['candidates' => []];

        $q = TruckingShipment::where('ext_vendor', $v)
            ->where('ext_fee', '>', 0)
            ->whereNotNull('gio_xe_den');
        if ($from) $q->where('gio_xe_den', '>=', $from . ' 00:00:00');
        if ($to)   $q->where('gio_xe_den', '<=', $to . ' 23:59:59');

        $rows = $q->orderBy('gio_xe_den')->get();
        $out = [];
        foreach ($rows as $s) {
            $sheet = strtoupper((string) $s->sheet);
            $contLabel = $sheet === 'HPH'
                ? ($s->qty . ' × ' . $s->cont_type)
                : (($s->cont_no ?: $s->cont_type) . ($s->cont_no ? ' · ' . $s->cont_type : ''));
            $out[] = [
                'id'        => $s->id,
                'booking'   => $s->booking ?? '',
                'sheet'     => $sheet,
                'bks'       => $s->bks_vao ?: ($s->bks_ra ?: ''),
                'from'      => $s->from_loc ?? '',
                'to'        => $s->to_loc ?? '',
                'contLabel' => $contLabel,
                'date'      => $this->outDate($s->gio_xe_den),
                'fee'       => (int) round((float) $s->ext_fee),
                'note'      => trim((string) $s->ghi_chu),
            ];
        }
        return ['candidates' => $out];
    }

    /**
     * Lưu bảng kê xe ngoài. Payload: {id?, no, vendor, date, from, to, note?, lines[], payments[]}.
     * total = Σ line.fee (nguồn chân lý — server tự cộng). Bulk insert lines + payments.
     */
    public function saveExtStatement(array $data, ?TruckingExtStatement $st = null): TruckingExtStatement
    {
        return DB::transaction(function () use ($data, $st) {
            $total = 0;
            foreach (($data['lines'] ?? []) as $l) $total += $this->inMoney($l['fee'] ?? null) ?? 0;

            $st ??= new TruckingExtStatement();
            $st->fill([
                'no'          => $data['no'] ?? $st->no,
                'ext_vendor'  => $this->str($data['vendor'] ?? null),
                'date'        => $this->inDate($data['date'] ?? null),
                'period_from' => $this->inDate($data['from'] ?? null),
                'period_to'   => $this->inDate($data['to'] ?? null),
                'total'       => $total,
                'note'        => $this->str($data['note'] ?? null),
            ]);
            $st->save();

            $now = Carbon::now();
            $st->lines()->delete();
            $linesBatch = [];
            foreach (($data['lines'] ?? []) as $i => $l) {
                $linesBatch[] = [
                    'ext_statement_id' => $st->id,
                    'shipment_id'      => $l['id'] ?? null,
                    'booking'          => $this->str($l['booking'] ?? null),
                    'sheet'            => $this->str($l['sheet'] ?? null),
                    'bks'              => $this->str($l['bks'] ?? null),
                    'from_loc'         => $this->str($l['from'] ?? null),
                    'to_loc'           => $this->str($l['to'] ?? null),
                    'cont_label'       => $this->str($l['contLabel'] ?? null),
                    'date'             => $this->inDate($l['date'] ?? null),
                    'fee'              => $this->inMoney($l['fee'] ?? null) ?? 0,
                    'note'             => $this->str($l['note'] ?? null),
                    'sort'             => $i,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
            if ($linesBatch) TruckingExtStatementLine::insert($linesBatch);

            $st->payments()->delete();
            $paymentsBatch = [];
            foreach (($data['payments'] ?? []) as $i => $p) {
                $paymentsBatch[] = [
                    'ext_statement_id' => $st->id,
                    'date'             => $this->inDate($p['date'] ?? null),
                    'amount'           => $this->inMoney($p['amount'] ?? null),
                    'note'             => $this->str($p['note'] ?? null),
                    'sort'             => $i,
                    'created_at'       => $now,
                    'updated_at'       => $now,
                ];
            }
            if ($paymentsBatch) TruckingExtStatementPayment::insert($paymentsBatch);

            return $st->fresh(['lines', 'payments']);
        });
    }

    /**
     * Danh sách bảng kê xe ngoài cho trang list — tóm tắt + payments (không nạp lines).
     * Lọc optional theo vendor/from/to (kỳ = period_to). Sắp mới nhất.
     *
     * @param array{vendor?:?string,from?:?string,to?:?string} $filters
     */
    public function extStatementsForList(array $filters = []): array
    {
        $q = TruckingExtStatement::with('payments');
        $vendor = trim((string) ($filters['vendor'] ?? ''));
        if ($vendor !== '') $q->where('ext_vendor', $vendor);
        if (! empty($filters['from'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '>=', $filters['from'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '>=', $filters['from']); });
        });
        if (! empty($filters['to'])) $q->where(function ($w) use ($filters) {
            $w->where('period_to', '<=', $filters['to'])
              ->orWhere(function ($a) use ($filters) { $a->whereNull('period_to')->where('date', '<=', $filters['to']); });
        });

        return $q->orderByDesc('id')->get()->map(function ($st) {
            $total = (int) round((float) $st->total);
            $paid  = (int) $st->payments->reduce(fn ($a, $p) => $a + (int) round((float) $p->amount), 0);
            return [
                'id'     => $st->id,
                'hashid' => Hashid::encode($st->id),
                'no'     => $st->no,
                'vendor' => $st->ext_vendor ?? '',
                'date'   => $this->outDate($st->date),
                'from'   => $this->outDate($st->period_from),
                'to'     => $this->outDate($st->period_to),
                'total'  => $total,
                'paid'   => $paid,
                'conNo'  => $total - $paid,
                'count'  => $st->lines()->count(),
            ];
        })->all();
    }

    /** Chi tiết đầy đủ 1 bảng kê xe ngoài: lines + payments + total + paid + conNo. */
    public function extStatementToArray(TruckingExtStatement $st): array
    {
        $total = (int) round((float) $st->total);
        $paid  = (int) $st->payments->reduce(fn ($a, $p) => $a + (int) round((float) $p->amount), 0);
        return [
            'id'     => $st->id,
            'hashid' => Hashid::encode($st->id),
            'no'     => $st->no,
            'vendor' => $st->ext_vendor ?? '',
            'date'   => $this->outDate($st->date),
            'from'   => $this->outDate($st->period_from),
            'to'     => $this->outDate($st->period_to),
            'note'   => $st->note ?? '',
            'total'  => $total,
            'paid'   => $paid,
            'conNo'  => $total - $paid,
            'lines'  => $st->lines->map(fn ($l) => [
                'id'        => $l->shipment_id ?? $l->id,
                'booking'   => $l->booking ?? '',
                'sheet'     => $l->sheet ?? '',
                'bks'       => $l->bks ?? '',
                'from'      => $l->from_loc ?? '',
                'to'        => $l->to_loc ?? '',
                'contLabel' => $l->cont_label ?? '',
                'date'      => $this->outDate($l->date),
                'fee'       => (int) round((float) $l->fee),
                'note'      => $l->note ?? '',
            ])->all(),
            'payments' => $st->payments->map(fn ($p) => [
                'id'     => $p->id,
                'date'   => $this->outDate($p->date),
                'amount' => $this->outMoney($p->amount),
                'note'   => $p->note ?? '',
            ])->all(),
        ];
    }
}
