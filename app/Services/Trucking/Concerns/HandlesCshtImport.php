<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingCostItem;
use App\Models\TruckingShipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import CSHT — nạp hàng loạt phí CSHT + Số tiền thanh lý vào CHI PHÍ LÔ HÀNG theo SỐ CONT.
 * Mỗi dòng file (khớp 1 lô theo cont) ghi/ghi đè tối đa 2 khoản: "CSHT" và "Thanh lí".
 * Số HĐ / Ngày HĐ / Ghi chú áp cho cả 2 khoản. Đối chiếu Nhập/Xuất; lệch = lỗi, chặn import.
 */
trait HandlesCshtImport
{
    private const CSHT_ITEM = 'CSHT';
    private const THANHLY_ITEM = 'Thanh lí';

    /** Dry-run: chỉ kiểm tra, không ghi DB. */
    public function validateCshtImport(string $sheet, array $rows): array
    {
        $errors = $this->validateCshtRows($sheet, $rows);
        return ['valid' => empty($errors), 'total' => count($rows), 'errors' => $errors];
    }

    /**
     * Import CSHT — ALL-OR-NOTHING: 1 dòng lỗi là KHÔNG ghi gì cả.
     * GHI ĐÈ dòng CSHT/Thanh lí sẵn có của lô (không nhân đôi khi import lại).
     */
    public function importCshtImport(string $sheet, array $rows): array
    {
        $errors = $this->validateCshtRows($sheet, $rows);
        if ($errors) {
            return ['valid' => false, 'updated' => 0, 'errors' => $errors, 'total' => count($rows)];
        }

        // Khoản CSHT / Thanh lí (để lấy màu theo dõi + VAT% mặc định cho dòng tạo mới).
        $cshtItem = TruckingCostItem::where('name', self::CSHT_ITEM)->first();
        $tlItem   = TruckingCostItem::where('name', self::THANHLY_ITEM)->first();

        return DB::transaction(function () use ($sheet, $rows, $cshtItem, $tlItem) {
            $map = $this->cshtShipmentMap($sheet, $rows);   // upper(cont) => lô (duy nhất)
            $touched = [];
            $updated = 0;
            foreach ($rows as $row) {
                $cont = mb_strtoupper(trim((string) ($row['contNo'] ?? '')));
                $s = $map[$cont] ?? null;
                if (! $s) continue;   // đã validate — không nên xảy ra

                $date = $this->cshtDate($row);
                $inv  = $this->str($row['invoiceNo'] ?? null);
                $note = $this->str($row['note'] ?? null);
                $cshtAmt = (int) round((float) $this->inMoney($row['csht'] ?? null));
                $tlAmt   = (int) round((float) $this->inMoney($row['thanhLy'] ?? null));

                if ($cshtAmt !== 0) { $this->upsertCshtLine($s, self::CSHT_ITEM, $cshtItem, $cshtAmt, $inv, $date, $note); $updated++; }
                if ($tlAmt !== 0)   { $this->upsertCshtLine($s, self::THANHLY_ITEM, $tlItem, $tlAmt, $inv, $date, $note); $updated++; }
                $touched[$s->id] = $s;
            }
            // Đồng bộ derived (cost_item_id, totals) cho các lô đã đụng.
            foreach ($touched as $s) $this->recomputeShipmentDerived($s, ['cost']);

            return ['valid' => true, 'updated' => $updated, 'shipments' => count($touched), 'errors' => [], 'total' => count($rows)];
        });
    }

    /** Ghi đè (hoặc tạo) 1 dòng chi phí theo (lô, tên khoản). Chỉ đụng số tiền / số HĐ / ngày / ghi chú. */
    private function upsertCshtLine(TruckingShipment $s, string $itemName, ?TruckingCostItem $item, int $amount, ?string $inv, ?string $date, ?string $note): void
    {
        // Dòng sẵn có: ưu tiên khớp theo cost_item_id (bền khi đổi tên), fallback theo tên khoản.
        $line = $s->costLines()
            ->where(fn ($q) => $item ? $q->where('cost_item_id', $item->id)->orWhere('item', $itemName) : $q->where('item', $itemName))
            ->orderBy('sort')->first();

        $fields = ['item' => $itemName, 'amount' => $amount, 'invoice_no' => $inv, 'date' => $date, 'note' => $note];
        if ($line) {
            $line->fill($fields)->save();
            return;
        }
        $s->costLines()->create($fields + [
            'vat'          => $item?->vat ?? 0,
            'color'        => $item?->color,
            'cost_item_id' => $item?->id,
            'billable'     => false,
            'sort'         => (int) $s->costLines()->max('sort') + 1,
        ]);
    }

    /** [UPPER(cont) => Collection các lô] cho các cont có trong file (1 query whereIn). */
    private function cshtShipmentsByCont(string $sheet, array $rows): Collection
    {
        $conts = collect($rows)
            ->map(fn ($r) => mb_strtoupper(trim((string) ($r['contNo'] ?? ''))))
            ->filter()->unique()->values();
        if ($conts->isEmpty()) return collect();

        return TruckingShipment::ofSheet($sheet)
            ->whereIn(DB::raw('UPPER(cont_no)'), $conts->all())
            ->get()
            ->groupBy(fn ($s) => mb_strtoupper(trim((string) $s->cont_no)));
    }

    /** [UPPER(cont) => lô] CHỈ với cont khớp đúng 1 lô (validate đã đảm bảo). */
    private function cshtShipmentMap(string $sheet, array $rows): array
    {
        return $this->cshtShipmentsByCont($sheet, $rows)
            ->filter(fn ($g) => $g->count() === 1)
            ->map(fn ($g) => $g->first())
            ->all();
    }

    /** Kiểm tra từng dòng → mảng lỗi [{line, cont, io, reasons[]}]. */
    private function validateCshtRows(string $sheet, array $rows): array
    {
        $errors = [];
        $byCont = $this->cshtShipmentsByCont($sheet, $rows);
        $hasCsht = TruckingCostItem::where('name', self::CSHT_ITEM)->exists();
        $hasTl   = TruckingCostItem::where('name', self::THANHLY_ITEM)->exists();

        foreach ($rows as $i => $row) {
            $reasons = [];
            $cont  = trim((string) ($row['contNo'] ?? ''));
            $contU = mb_strtoupper($cont);

            if ($cont === '') {
                $reasons[] = 'Thiếu Số cont';
            } else {
                $found = $byCont[$contU] ?? collect();
                if ($found->isEmpty()) {
                    $reasons[] = "Số cont “{$cont}” không có trong danh sách lô";
                } elseif ($found->count() > 1) {
                    $reasons[] = "Số cont “{$cont}” trùng ở {$found->count()} lô — không xác định được lô nào";
                } else {
                    // Đối chiếu Nhập/Xuất — lệch = lỗi (chặn import).
                    $fileIo = $this->normIo($row['io'] ?? '');
                    if ($fileIo !== '') {
                        $shipIo = $this->normIo($found->first()->io);
                        if ($shipIo === '') {
                            $reasons[] = 'Lô chưa đặt Nhập/Xuất nên không đối chiếu được — bỏ trống cột Nhập/Xuất hoặc đặt cho lô';
                        } elseif ($fileIo !== $shipIo) {
                            $reasons[] = 'Nhập/Xuất “' . trim((string) ($row['io'] ?? '')) . '” lệch với lô (' . $this->ioLabel($shipIo) . ')';
                        }
                    }
                }
            }

            $dateRaw = trim((string) ($row['dateRaw'] ?? ($row['date'] ?? '')));
            if ($dateRaw !== '' && ! $this->isValidDateStr($dateRaw)) {
                $reasons[] = "Ngày HĐ “{$dateRaw}” sai định dạng (cần dd/mm/yyyy)";
            }

            $cshtAmt = (int) round((float) $this->inMoney($row['csht'] ?? null));
            $tlAmt   = (int) round((float) $this->inMoney($row['thanhLy'] ?? null));
            if ($cshtAmt === 0 && $tlAmt === 0) $reasons[] = 'Không có phí CSHT lẫn Số tiền thanh lý';
            if ($cshtAmt < 0 || $tlAmt < 0)     $reasons[] = 'Số tiền không được âm';
            if ($cshtAmt !== 0 && ! $hasCsht)   $reasons[] = 'Chưa có khoản “CSHT” trong Cài đặt → Khoản chi phí';
            if ($tlAmt !== 0 && ! $hasTl)       $reasons[] = 'Chưa có khoản “Thanh lí” trong Cài đặt → Khoản chi phí';

            if ($reasons) {
                $errors[] = ['line' => $i + 1, 'cont' => $cont, 'io' => (string) ($row['io'] ?? ''), 'reasons' => $reasons];
            }
        }
        return $errors;
    }

    /** Ngày HĐ → 'Y-m-d': ưu tiên ISO `date` (frontend), fallback dd/mm/yyyy `dateRaw`. null nếu trống. */
    private function cshtDate(array $row): ?string
    {
        $iso = trim((string) ($row['date'] ?? ''));
        if ($iso !== '') return $this->inDate($iso);
        $raw = trim((string) ($row['dateRaw'] ?? ''));
        if ($raw === '') return null;
        if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})#', $raw, $m)) {
            $d = (int) $m[1]; $mo = (int) $m[2]; $y = (int) $m[3];
            if ($y < 100) $y += 2000;
            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }
        return $this->inDate($raw);
    }

    /** Chuẩn hóa Nhập/Xuất/Khác về khóa so sánh (bỏ dấu, mọi cách viết). '' nếu trống. */
    private function normIo($v): string
    {
        $s = trim((string) $v);
        if ($s === '') return '';
        $a = mb_strtolower(Str::ascii($s));
        if (str_starts_with($a, 'nh') || str_contains($a, 'import')) return 'nhap';
        if (str_starts_with($a, 'xu') || str_contains($a, 'export')) return 'xuat';
        if (str_starts_with($a, 'kh') || str_contains($a, 'other'))  return 'khac';
        return $a;
    }

    private function ioLabel(string $norm): string
    {
        return ['nhap' => 'Nhập', 'xuat' => 'Xuất', 'khac' => 'Khác'][$norm] ?? $norm;
    }
}
