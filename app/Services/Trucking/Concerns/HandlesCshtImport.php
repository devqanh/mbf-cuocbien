<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingCostItem;
use App\Models\TruckingShipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import CSHT / CHI PHÍ LÔ HÀNG — 1 dòng file = 1 lô, mỗi cột khoản chi phí (theo danh mục) = số tiền.
 *  - Khớp lô theo ID LÔ (cột có trong file "Xuất chi phí lô"); dòng không có ID thì khớp theo SỐ CONT
 *    như file CSHT cũ (cont trùng nhiều lô → lỗi, hướng dẫn dùng file có ID).
 *  - Ô trống = KHÔNG đụng; số giống hiện tại = bỏ qua. Không bao giờ xóa dòng chi phí.
 *  - Lô chưa có khoản → tạo dòng; có 1 dòng → sửa số tiền; có nhiều dòng cùng khoản → chỉ nhận khi
 *    số trong file bằng TỔNG hiện tại (không đoán được dòng nào cần sửa).
 *  - Số HĐ / Ngày HĐ / Ghi chú (dùng chung cả dòng) chỉ áp cho CSHT + Thanh lí như mẫu CSHT cũ.
 *  - Phí mở tờ khai lấy từ tờ khai → không sửa qua đây. Cước xe ngoài chỉ sửa được khi lô đã có dòng
 *    Thuê xe ngoài (ext_fee tự chốt lại qua recompute).
 *  - ALL-OR-NOTHING: 1 dòng lỗi là không ghi gì.
 */
trait HandlesCshtImport
{
    private const CSHT_ITEM = 'CSHT';
    private const THANHLY_ITEM = 'Thanh lí';
    private const EXT_TRUCK_ITEM = 'Cước xe ngoài';

    /** Dry-run: chỉ kiểm tra + thống kê sẽ tạo / sửa / giữ nguyên, không ghi DB. */
    public function validateCshtImport(string $sheet, array $rows): array
    {
        $plan = $this->planCshtImport($sheet, $rows);
        return [
            'valid' => empty($plan['errors']), 'total' => count($rows), 'errors' => $plan['errors'],
            'warnings' => $plan['warnings'], 'items' => $plan['items'], 'columns' => $plan['columns'], 'stats' => $plan['stats'],
        ];
    }

    /** Import — ALL-OR-NOTHING. Chỉ ghi các dòng chi phí thực sự đổi. */
    public function importCshtImport(string $sheet, array $rows): array
    {
        $plan = $this->planCshtImport($sheet, $rows);
        if ($plan['errors']) {
            return ['valid' => false, 'updated' => 0, 'created' => 0, 'errors' => $plan['errors'], 'total' => count($rows)];
        }

        return DB::transaction(function () use ($plan, $rows) {
            $touched = [];
            $nextSort = [];   // shipment_id => sort kế tiếp cho dòng tạo mới
            $created = 0; $updated = 0;
            foreach ($plan['ops'] as $op) {
                $s = $op['shipment'];
                if ($op['type'] === 'create') {
                    $nextSort[$s->id] ??= (int) $s->costLines()->max('sort') + 1;
                    $s->costLines()->create($op['fields'] + ['sort' => $nextSort[$s->id]++]);
                    $created++;
                } else {
                    $op['line']->fill($op['fields'])->save();
                    $updated++;
                }
                $touched[$s->id] = $s;
            }
            // Đồng bộ derived (cost_item_id, totals, ext_fee) cho các lô đã đụng.
            foreach ($touched as $s) { $s->unsetRelation('costLines'); $this->recomputeShipmentDerived($s, ['cost']); }

            return ['valid' => true, 'created' => $created, 'updated' => $updated, 'shipments' => count($touched),
                'errors' => [], 'total' => count($rows)];
        });
    }

    /**
     * Lập kế hoạch ghi (dùng chung cho Kiểm tra và Import để 2 bước luôn khớp nhau).
     *
     * @return array{errors:array, warnings:array, items:array, columns:array, stats:array, ops:array}
     */
    private function planCshtImport(string $sheet, array $rows): array
    {
        $rows = array_map(fn ($r) => $this->cshtLegacyRow(is_array($r) ? $r : []), $rows);
        $catalog = TruckingCostItem::orderBy('sort')->orderBy('name')->get(['id', 'name', 'vat', 'color']);

        // 1) Cột tiền trong file → khoản chi phí trong danh mục (theo tên, bỏ dấu/khoảng trắng).
        $headers = [];
        foreach ($rows as $r) foreach (array_keys((array) ($r['amounts'] ?? [])) as $h) $headers[$h] = true;
        $columns = []; $items = []; $warnings = []; $errors = [];
        $byItem = [];   // item id => header đã nhận (bắt 2 cột cùng 1 khoản)
        foreach (array_keys($headers) as $h) {
            $item = $this->cshtItemForHeader((string) $h, $catalog);
            $hasValue = collect($rows)->contains(fn ($r) => trim((string) (($r['amounts'] ?? [])[$h] ?? '')) !== '');
            if (! $item) {
                $columns[$h] = null;
                if ($hasValue) $warnings[] = "Bỏ qua cột “{$h}” — không phải khoản chi phí trong Cài đặt → Khoản chi phí";
                continue;
            }
            if (isset($byItem[$item->id])) {
                $errors[] = ['line' => 0, 'id' => '', 'cont' => '', 'io' => '', 'reasons' => ["2 cột “{$byItem[$item->id]}” và “{$h}” cùng là khoản {$item->name} — giữ 1 cột"]];
                continue;
            }
            $byItem[$item->id] = $h;
            $columns[$h] = $item->name;
            $items[] = $item->name;
        }
        $itemByHeader = [];
        foreach ($columns as $h => $name) if ($name !== null) $itemByHeader[$h] = $catalog->firstWhere('name', $name);
        // Thứ tự cột xem trước = thứ tự danh mục (CSHT, Thanh lí… như popup).
        $items = $catalog->pluck('name')->filter(fn ($n) => in_array($n, $items, true))->values()->all();

        // 2) Nạp lô: theo ID (dòng có ID) + theo cont (dòng chỉ có cont) — mỗi loại 1 query, kèm dòng chi phí.
        $ids = collect($rows)->map(fn ($r) => (int) ($r['id'] ?? 0))->filter(fn ($v) => $v > 0)->unique()->values();
        $byId = $ids->isEmpty() ? collect()
            : TruckingShipment::ofSheet($sheet)->whereIn('id', $ids->all())->with('costLines')->get()->keyBy('id');
        $byCont = $this->cshtShipmentsByCont($sheet, array_values(array_filter($rows, fn ($r) => (int) ($r['id'] ?? 0) <= 0)));

        $stats = ['create' => 0, 'update' => 0, 'same' => 0, 'shipments' => 0];
        $ops = []; $seen = []; $lots = [];
        foreach ($rows as $i => $row) {
            $line = (int) ($row['line'] ?? 0) ?: $i + 1;   // dòng THẬT trong file Excel
            $idRaw = trim((string) ($row['id'] ?? ''));
            $cont  = trim((string) ($row['contNo'] ?? ''));
            $amounts = [];   // item id => [item, số tiền]
            foreach ($itemByHeader as $h => $item) {
                $v = trim((string) (($row['amounts'] ?? [])[$h] ?? ''));
                if ($v === '') continue;
                $amt = (int) round((float) $this->inMoney($v));
                if ($amt > 0) $amounts[$item->id] = [$item, $amt];
            }
            $shared = array_filter([
                'invoice_no' => $this->str($row['invoiceNo'] ?? null),
                'date'       => $this->cshtDate($row),
                'note'       => $this->str($row['note'] ?? null),
            ], fn ($v) => $v !== null && $v !== '');
            // Dòng không có số tiền lẫn Số HĐ/Ngày/Ghi chú = lô chưa điền (file xuất có đủ mọi lô) → bỏ qua.
            if (! $amounts && ! $shared) continue;

            $reasons = [];
            $s = null;
            if ($idRaw !== '') {
                $id = ctype_digit($idRaw) ? (int) $idRaw : 0;
                $s = $id ? $byId->get($id) : null;
                if (! $s) {
                    $reasons[] = "ID lô “{$idRaw}” không có trong danh sách lô";
                } elseif ($cont !== '' && trim((string) $s->cont_no) !== '' && mb_strtoupper($cont) !== mb_strtoupper(trim((string) $s->cont_no))) {
                    $reasons[] = "ID lô {$s->id} là cont " . trim((string) $s->cont_no) . " nhưng file ghi “{$cont}” — kiểm tra lại dòng này";
                    $s = null;
                }
            } elseif ($cont === '') {
                $reasons[] = 'Thiếu ID lô và Số cont';
            } else {
                $found = $byCont[mb_strtoupper($cont)] ?? collect();
                if ($found->isEmpty()) {
                    $reasons[] = "Số cont “{$cont}” không có trong danh sách lô";
                } elseif ($found->count() > 1) {
                    $reasons[] = "Số cont “{$cont}” trùng ở {$found->count()} lô (ID " . $found->pluck('id')->implode(', ')
                        . ') — dùng nút “Xuất chi phí lô” để có cột ID LÔ rồi import lại';
                } else {
                    $s = $found->first();
                }
            }

            if ($s) {
                if (isset($seen[$s->id])) { $reasons[] = "Trùng lô với dòng {$seen[$s->id]} trong file"; }
                else $seen[$s->id] = $line;
                // Đối chiếu Nhập/Xuất — lệch = lỗi (chặn import).
                $fileIo = $this->normIo($row['io'] ?? '');
                if ($fileIo !== '') {
                    $shipIo = $this->normIo($s->io);
                    if ($shipIo === '') {
                        $reasons[] = 'Lô chưa đặt Nhập/Xuất nên không đối chiếu được — bỏ trống cột Nhập/Xuất hoặc đặt cho lô';
                    } elseif ($fileIo !== $shipIo) {
                        $reasons[] = 'Nhập/Xuất “' . trim((string) ($row['io'] ?? '')) . '” lệch với lô (' . $this->ioLabel($shipIo) . ')';
                    }
                }
            }

            $dateRaw = trim((string) ($row['dateRaw'] ?? ($row['date'] ?? '')));
            if ($dateRaw !== '' && ! $this->isValidDateStr($dateRaw)) {
                $reasons[] = "Ngày HĐ “{$dateRaw}” sai định dạng (cần dd/mm/yyyy)";
            }
            $hasCsTl = (bool) array_filter($amounts, fn ($a) => in_array($a[0]->name, [self::CSHT_ITEM, self::THANHLY_ITEM], true));
            if ($shared && ! $hasCsTl) {
                $reasons[] = 'Số HĐ / Ngày HĐ / Ghi chú chỉ áp cho CSHT và Thanh lí — dòng chưa có số tiền CSHT hoặc Thanh lí';
            }

            // Từng khoản → tạo / sửa / giữ nguyên.
            $rowOps = [];
            if ($s && ! $reasons) {
                foreach ($amounts as [$item, $amt]) {
                    $lines = $s->costLines->filter(fn ($c) => (int) $c->cost_item_id === (int) $item->id
                        || $this->cshtNorm((string) $c->item) === $this->cshtNorm($item->name))->values();
                    $cur = (int) $lines->sum(fn ($c) => (int) round((float) $c->amount));
                    $isCsTl = in_array($item->name, [self::CSHT_ITEM, self::THANHLY_ITEM], true);

                    if ($item->name === self::DECL_FEE_ITEM || $lines->contains(fn ($c) => $c->src === 'thanhLyFee')) {
                        if ($amt !== $cur) $reasons[] = "{$item->name} lấy từ tờ khai (Hải quan) — sửa ở popup lô, không import được";
                        else $stats['same']++;
                        continue;
                    }
                    if ($lines->isEmpty()) {
                        if ($item->name === self::EXT_TRUCK_ITEM) { $reasons[] = 'Lô chưa có Cước xe ngoài — chọn Thuê xe ngoài ở Thông tin lô trước'; continue; }
                        $rowOps[] = ['type' => 'create', 'shipment' => $s, 'fields' => [
                            'item' => $item->name, 'amount' => $amt, 'cost_item_id' => $item->id,
                            'vat' => $item->vat ?? 0, 'color' => $item->color, 'billable' => false,
                        ] + ($isCsTl ? $shared : [])];
                        continue;
                    }
                    if ($lines->count() > 1) {
                        if ($amt === $cur) $stats['same']++;
                        else $reasons[] = "Lô có {$lines->count()} dòng {$item->name} (tổng " . number_format($cur, 0, ',', '.') . ') — sửa trực tiếp trong popup Chi phí lô hàng';
                        continue;
                    }
                    $c = $lines->first();
                    $fields = [];
                    if ($amt !== $cur) $fields['amount'] = $amt;
                    if ($isCsTl) {
                        foreach ($shared as $k => $v) {
                            $old = $k === 'date' ? $this->outDate($c->date) : trim((string) ($c->{$k} ?? ''));
                            if ((string) $old !== (string) $v) $fields[$k] = $v;
                        }
                    }
                    if ($fields) $rowOps[] = ['type' => 'update', 'shipment' => $s, 'line' => $c, 'fields' => $fields];
                    else $stats['same']++;
                }
            }

            if ($reasons) {
                $errors[] = ['line' => $line, 'id' => $idRaw !== '' ? $idRaw : ($s ? (string) $s->id : ''), 'cont' => $cont,
                    'io' => (string) ($row['io'] ?? ''), 'reasons' => $reasons];
                continue;
            }
            foreach ($rowOps as $op) { $ops[] = $op; $stats[$op['type']]++; $lots[$op['shipment']->id] = true; }
        }
        $stats['shipments'] = count($lots);

        return ['errors' => $errors, 'warnings' => $warnings, 'items' => $items, 'columns' => $columns, 'stats' => $stats, 'ops' => $ops];
    }

    /** File CSHT cũ gửi csht / thanhLy riêng → gom vào amounts như 2 cột khoản. */
    private function cshtLegacyRow(array $r): array
    {
        $amounts = is_array($r['amounts'] ?? null) ? $r['amounts'] : [];
        if (array_key_exists('csht', $r) && ! array_key_exists('PHÍ CSHT', $amounts)) $amounts['PHÍ CSHT'] = (string) ($r['csht'] ?? '');
        if (array_key_exists('thanhLy', $r) && ! array_key_exists('SỐ TIỀN THANH LÝ', $amounts)) $amounts['SỐ TIỀN THANH LÝ'] = (string) ($r['thanhLy'] ?? '');
        $r['amounts'] = $amounts;
        return $r;
    }

    /** Khóa so tên khoản: bỏ dấu, thường, bỏ ký tự ngoài chữ/số, y→i ("Thanh lý" == "Thanh lí"). */
    private function cshtNorm(string $s): string
    {
        $a = mb_strtolower(Str::ascii($s));
        return str_replace('y', 'i', preg_replace('/[^a-z0-9]/', '', $a) ?? '');
    }

    /** Tiêu đề cột → khoản trong danh mục. "PHÍ CSHT" / "SỐ TIỀN THANH LÝ" (mẫu cũ) và tiền tố "phí"/"số tiền" vẫn nhận. */
    private function cshtItemForHeader(string $h, Collection $catalog): ?TruckingCostItem
    {
        $n = $this->cshtNorm(str_replace('*', '', $h));
        if ($n === '') return null;
        $byNorm = $catalog->keyBy(fn ($c) => $this->cshtNorm($c->name));
        foreach ([$n, preg_replace('/^(sotien|phi|tien)/', '', $n)] as $k) {
            if ($k !== '' && $byNorm->has($k)) return $byNorm->get($k);
        }
        if (str_contains($n, 'csht')) return $byNorm->get($this->cshtNorm(self::CSHT_ITEM));
        if (str_contains($n, 'thanhli')) return $byNorm->get($this->cshtNorm(self::THANHLY_ITEM));
        return null;
    }

    /** [UPPER(cont) => Collection các lô] cho các cont có trong file (1 query whereIn, kèm dòng chi phí). */
    private function cshtShipmentsByCont(string $sheet, array $rows): Collection
    {
        $conts = collect($rows)
            ->map(fn ($r) => mb_strtoupper(trim((string) ($r['contNo'] ?? ''))))
            ->filter()->unique()->values();
        if ($conts->isEmpty()) return collect();

        return TruckingShipment::ofSheet($sheet)
            ->whereIn(DB::raw('UPPER(cont_no)'), $conts->all())
            ->with('costLines')
            ->get()
            ->groupBy(fn ($s) => mb_strtoupper(trim((string) $s->cont_no)));
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
