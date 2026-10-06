<?php

namespace App\Services\Trucking\Concerns;

use App\Models\TruckingCostItem;
use App\Models\TruckingPayer;
use App\Models\TruckingShipment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Import CSHT / CHI PHÍ LÔ HÀNG — 1 dòng file = 1 lô.
 *  - File "Xuất chi phí lô" (2 dòng tiêu đề): mỗi KHOẢN là 1 NHÓM cột (Số tiền · Vat · Số HĐ · Người chi ·
 *    Ngày HĐ · Ghi chú) → frontend gửi `groups[nhãn nhóm] = {amount, vat, invoiceNo, payer, date, note}`.
 *    Cột lẻ mang tên khoản (vd "CHI HỘ" = Chi hộ LCC) → `amounts[tiêu đề]`. Cột NHÃN → `tags`.
 *  - File 1 dòng tiêu đề / mẫu CSHT cũ: `amounts[tiêu đề]` + Số HĐ / Ngày HĐ / Ghi chú dùng chung, chỉ áp
 *    cho CSHT + Thanh lí (như trước).
 *  - Khớp lô theo ID LÔ; dòng không có ID thì theo SỐ CONT (cont trùng nhiều lô → lỗi).
 *  - Ô trống = KHÔNG đụng; giá trị giống hiện tại = bỏ qua. Không bao giờ xóa dòng chi phí.
 *  - Lô chưa có khoản → tạo dòng (cần Số tiền); có 1 dòng → sửa các ô có giá trị; có nhiều dòng cùng
 *    khoản → chỉ nhận khi không đổi gì (không đoán được dòng nào cần sửa).
 *  - Phí mở tờ khai lấy từ tờ khai → không sửa qua đây. Cước xe ngoài chỉ sửa dòng có sẵn.
 *  - NHÃN: ô có giá trị thì nhãn của lô = đúng danh sách trong ô (cách nhau dấu phẩy); ô trống = không đổi.
 *  - ALL-OR-NOTHING: 1 dòng lỗi là không ghi gì.
 */
trait HandlesCshtImport
{
    private const CSHT_ITEM = 'CSHT';
    private const THANHLY_ITEM = 'Thanh lí';
    private const EXT_TRUCK_ITEM = 'Cước xe ngoài';
    private const CSHT_FIELD_LABELS = ['amount' => 'Số tiền', 'vat' => 'Vat', 'invoice_no' => 'Số HĐ', 'payer' => 'Người chi', 'date' => 'Ngày HĐ', 'note' => 'Ghi chú'];

    /** Dry-run: chỉ kiểm tra + thống kê sẽ tạo / sửa / giữ nguyên, không ghi DB. */
    public function validateCshtImport(string $sheet, array $rows): array
    {
        $plan = $this->planCshtImport($sheet, $rows);
        return [
            'valid' => empty($plan['errors']), 'total' => count($rows), 'errors' => $plan['errors'],
            'warnings' => $plan['warnings'], 'items' => $plan['items'], 'columns' => $plan['columns'], 'stats' => $plan['stats'],
        ];
    }

    /** Import — ALL-OR-NOTHING. Chỉ ghi những gì thực sự đổi. */
    public function importCshtImport(string $sheet, array $rows): array
    {
        $plan = $this->planCshtImport($sheet, $rows);
        if ($plan['errors']) {
            return ['valid' => false, 'updated' => 0, 'created' => 0, 'tags' => 0, 'errors' => $plan['errors'], 'total' => count($rows)];
        }

        return DB::transaction(function () use ($plan, $rows) {
            $touched = []; $costTouched = [];
            $nextSort = [];   // shipment_id => sort kế tiếp cho dòng tạo mới
            $created = 0; $updated = 0; $tags = 0;
            foreach ($plan['ops'] as $op) {
                $s = $op['shipment'];
                if ($op['type'] === 'tags') {
                    $s->tags = $op['tags'];
                    $s->save();
                    $tags++;
                } elseif ($op['type'] === 'create') {
                    $nextSort[$s->id] ??= (int) $s->costLines()->max('sort') + 1;
                    $s->costLines()->create($op['fields'] + ['sort' => $nextSort[$s->id]++]);
                    $created++; $costTouched[$s->id] = $s;
                } else {
                    $op['line']->fill($op['fields'])->save();
                    $updated++; $costTouched[$s->id] = $s;
                }
                $touched[$s->id] = true;
            }
            // Đồng bộ derived (cost_item_id, payer_id, totals, ext_fee) cho các lô có đổi chi phí.
            foreach ($costTouched as $s) { $s->unsetRelation('costLines'); $this->recomputeShipmentDerived($s, ['cost']); }

            return ['valid' => true, 'created' => $created, 'updated' => $updated, 'tags' => $tags, 'shipments' => count($touched),
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
        $errors = []; $warnings = [];

        // 1) Cột / nhóm trong file → khoản chi phí trong danh mục. Mỗi khoản chỉ được xuất hiện 1 lần.
        $columns = [];       // nhãn cột hoặc nhóm → tên khoản (null = bỏ qua)
        $byItem = [];        // item id => nhãn đã nhận
        $resolve = function (string $label, bool $hasValue) use ($catalog, &$columns, &$byItem, &$errors, &$warnings) {
            if (array_key_exists($label, $columns)) return;
            $item = $this->cshtItemForHeader($label, $catalog);
            if (! $item) {
                $columns[$label] = null;
                if ($hasValue) $warnings[] = "Bỏ qua cột “{$label}” — không phải khoản chi phí trong Cài đặt → Khoản chi phí";
                return;
            }
            if (isset($byItem[$item->id])) {
                $errors[] = ['line' => 0, 'id' => '', 'cont' => '', 'io' => '', 'reasons' => ["“{$byItem[$item->id]}” và “{$label}” cùng là khoản {$item->name} — giữ 1 cột"]];
                $columns[$label] = null;
                return;
            }
            $byItem[$item->id] = $label;
            $columns[$label] = $item->name;
        };
        $labels = [];   // nhãn => có giá trị?
        foreach ($rows as $r) {
            foreach ((array) ($r['groups'] ?? []) as $g => $f) $labels[$g] = ($labels[$g] ?? false) || $this->cshtGroupHasValue((array) $f);
            foreach ((array) ($r['amounts'] ?? []) as $h => $v) $labels[$h] = ($labels[$h] ?? false) || trim((string) $v) !== '';
        }
        foreach ($labels as $label => $has) $resolve((string) $label, $has);
        $itemOf = fn (string $label) => ($columns[$label] ?? null) !== null ? $catalog->firstWhere('name', $columns[$label]) : null;
        // Thứ tự cột xem trước = thứ tự danh mục (như popup).
        $items = $catalog->pluck('name')->filter(fn ($n) => in_array($n, $columns, true))->values()->all();

        // Người chi lạ (không có trong danh mục) → chỉ cảnh báo, vẫn ghi (popup cũng cho gõ tên mới).
        $payerNames = TruckingPayer::pluck('name')->map(fn ($n) => mb_strtolower(trim((string) $n)))->all();
        $unknownPayers = [];

        // 2) Nạp lô: theo ID (dòng có ID) + theo cont (dòng chỉ có cont) — mỗi loại 1 query, kèm dòng chi phí.
        $ids = collect($rows)->map(fn ($r) => (int) ($r['id'] ?? 0))->filter(fn ($v) => $v > 0)->unique()->values();
        $byId = $ids->isEmpty() ? collect()
            : TruckingShipment::ofSheet($sheet)->whereIn('id', $ids->all())->with('costLines')->get()->keyBy('id');
        $byCont = $this->cshtShipmentsByCont($sheet, array_values(array_filter($rows, fn ($r) => (int) ($r['id'] ?? 0) <= 0)));

        $stats = ['create' => 0, 'update' => 0, 'same' => 0, 'tags' => 0, 'shipments' => 0];
        $ops = []; $seen = []; $lots = [];
        foreach ($rows as $i => $row) {
            $line = (int) ($row['line'] ?? 0) ?: $i + 1;   // dòng THẬT trong file Excel
            $idRaw = trim((string) ($row['id'] ?? ''));
            $cont  = trim((string) ($row['contNo'] ?? ''));
            $reasons = [];

            // Yêu cầu theo từng khoản: item id => [item, fields{amount?, vat?, invoice_no?, payer?, date?, note?}]
            $specs = [];
            foreach ((array) ($row['groups'] ?? []) as $label => $f) {
                $item = $itemOf((string) $label);
                if (! $item) continue;
                $fields = $this->cshtGroupFields((array) $f, $item->name, $reasons);
                if ($fields) $specs[$item->id] = [$item, $fields];
            }
            foreach ((array) ($row['amounts'] ?? []) as $h => $v) {
                $item = $itemOf((string) $h);
                $v = trim((string) $v);
                if (! $item || $v === '') continue;
                $amt = (int) round((float) $this->inMoney($v));
                if ($amt > 0) $specs[$item->id] = [$item, ['amount' => $amt]];
            }
            // Số HĐ / Ngày HĐ / Ghi chú dùng chung (file 1 dòng tiêu đề) → chỉ CSHT + Thanh lí có số tiền.
            $shared = array_filter([
                'invoice_no' => $this->str($row['invoiceNo'] ?? null),
                'date'       => $this->cshtDate($row),
                'note'       => $this->str($row['note'] ?? null),
            ], fn ($v) => $v !== null && $v !== '');
            $dateRaw = trim((string) ($row['dateRaw'] ?? ($row['date'] ?? '')));
            if ($dateRaw !== '' && ! $this->isValidDateStr($dateRaw)) {
                $reasons[] = "Ngày HĐ “{$dateRaw}” sai định dạng (cần dd/mm/yyyy)";
            }
            if ($shared) {
                $hit = false;
                foreach ($specs as $k => [$item, $fields]) {
                    if (in_array($item->name, [self::CSHT_ITEM, self::THANHLY_ITEM], true) && isset($fields['amount'])) {
                        $specs[$k][1] = $fields + $shared; $hit = true;
                    }
                }
                if (! $hit) $reasons[] = 'Số HĐ / Ngày HĐ / Ghi chú chỉ áp cho CSHT và Thanh lí — dòng chưa có số tiền CSHT hoặc Thanh lí';
            }
            $tagsRaw = trim((string) ($row['tags'] ?? ''));

            // Dòng không có gì để ghi = lô chưa điền (file xuất có đủ mọi lô) → bỏ qua.
            if (! $specs && ! $shared && $tagsRaw === '' && ! $reasons) continue;

            $s = $this->cshtFindShipment($idRaw, $cont, $byId, $byCont, $reasons);
            if ($s) {
                if (isset($seen[$s->id])) { $reasons[] = "Trùng lô với dòng {$seen[$s->id]} trong file"; }
                else $seen[$s->id] = $line;
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

            // Từng khoản → tạo / sửa / giữ nguyên.
            $rowOps = [];
            if ($s && ! $reasons) {
                foreach ($specs as [$item, $fields]) {
                    $lines = $s->costLines->filter(fn ($c) => (int) $c->cost_item_id === (int) $item->id
                        || $this->cshtNorm((string) $c->item) === $this->cshtNorm($item->name))->values();
                    $diff = $this->cshtDiff($lines, $fields);   // các ô KHÁC hiện tại (nhiều dòng: so tổng tiền / giá trị chung)

                    if ($item->name === self::DECL_FEE_ITEM || $lines->contains(fn ($c) => $c->src === 'thanhLyFee')) {
                        if ($diff) $reasons[] = "{$item->name} lấy từ tờ khai (Hải quan) — sửa ở popup lô, không import được";
                        else $stats['same']++;
                        continue;
                    }
                    if ($lines->isEmpty()) {
                        if ($item->name === self::EXT_TRUCK_ITEM) { $reasons[] = 'Lô chưa có Cước xe ngoài — chọn Thuê xe ngoài ở Thông tin lô trước'; continue; }
                        if (! isset($fields['amount'])) { $reasons[] = "Lô chưa có khoản {$item->name} — cần Số tiền để tạo"; continue; }
                        $rowOps[] = ['type' => 'create', 'shipment' => $s, 'fields' => $fields + [
                            'item' => $item->name, 'cost_item_id' => $item->id,
                            'vat' => $item->vat ?? 0, 'color' => $item->color, 'billable' => false,
                        ]];
                        continue;
                    }
                    if (! $diff) { $stats['same']++; continue; }
                    if ($lines->count() > 1) {
                        $reasons[] = "Lô có {$lines->count()} dòng {$item->name} — sửa trực tiếp trong popup Chi phí lô hàng ("
                            . implode(', ', array_map(fn ($k) => self::CSHT_FIELD_LABELS[$k] ?? $k, array_keys($diff))) . ' khác hiện tại)';
                        continue;
                    }
                    $rowOps[] = ['type' => 'update', 'shipment' => $s, 'line' => $lines->first(), 'fields' => $diff];
                }
                foreach ($specs as [$item, $f]) {
                    $p = trim((string) ($f['payer'] ?? ''));
                    if ($p !== '' && ! in_array(mb_strtolower($p), $payerNames, true)) $unknownPayers[$p] = true;
                }
                if ($tagsRaw !== '') {
                    $want = $this->cshtTags($tagsRaw);
                    $cur  = array_values(array_filter(array_map(fn ($t) => trim((string) $t), (array) ($s->tags ?? [])), fn ($t) => $t !== ''));
                    if ($want !== $cur) $rowOps[] = ['type' => 'tags', 'shipment' => $s, 'tags' => $want];
                    else $stats['same']++;
                }
            }

            if ($reasons) {
                $errors[] = ['line' => $line, 'id' => $idRaw !== '' ? $idRaw : ($s ? (string) $s->id : ''), 'cont' => $cont,
                    'io' => (string) ($row['io'] ?? ''), 'reasons' => array_values(array_unique($reasons))];
                continue;
            }
            foreach ($rowOps as $op) { $ops[] = $op; $stats[$op['type']]++; $lots[$op['shipment']->id] = true; }
        }
        $stats['shipments'] = count($lots);
        if ($unknownPayers) $warnings[] = 'Người chi chưa có trong danh mục (vẫn ghi): ' . implode(', ', array_keys($unknownPayers));

        return ['errors' => $errors, 'warnings' => $warnings, 'items' => $items, 'columns' => $columns, 'stats' => $stats, 'ops' => $ops];
    }

    /** Tìm lô theo ID (ưu tiên) hoặc số cont; ghi lý do vào $reasons nếu không xác định được. */
    private function cshtFindShipment(string $idRaw, string $cont, Collection $byId, Collection $byCont, array &$reasons): ?TruckingShipment
    {
        if ($idRaw !== '') {
            $s = ctype_digit($idRaw) ? $byId->get((int) $idRaw) : null;
            if (! $s) { $reasons[] = "ID lô “{$idRaw}” không có trong danh sách lô"; return null; }
            $sc = trim((string) $s->cont_no);
            if ($cont !== '' && $sc !== '' && mb_strtoupper($cont) !== mb_strtoupper($sc)) {
                $reasons[] = "ID lô {$s->id} là cont {$sc} nhưng file ghi “{$cont}” — kiểm tra lại dòng này";
                return null;
            }
            return $s;
        }
        if ($cont === '') { $reasons[] = 'Thiếu ID lô và Số cont'; return null; }
        $found = $byCont[mb_strtoupper($cont)] ?? collect();
        if ($found->isEmpty()) { $reasons[] = "Số cont “{$cont}” không có trong danh sách lô"; return null; }
        if ($found->count() > 1) {
            $reasons[] = "Số cont “{$cont}” trùng ở {$found->count()} lô (ID " . $found->pluck('id')->implode(', ')
                . ') — dùng nút “Xuất chi phí lô” để có cột ID LÔ rồi import lại';
            return null;
        }
        return $found->first();
    }

    /**
     * Ô của 1 nhóm khoản → trường dòng chi phí (chỉ ô có giá trị). Sai định dạng → ghi lý do.
     * amount: số tiền (>0) · vat: % (0–100) · date: ISO từ frontend, fallback dd/mm/yyyy.
     */
    private function cshtGroupFields(array $f, string $itemName, array &$reasons): array
    {
        $out = [];
        $amt = trim((string) ($f['amount'] ?? ''));
        if ($amt !== '') { $a = (int) round((float) $this->inMoney($amt)); if ($a > 0) $out['amount'] = $a; }
        $vat = trim((string) ($f['vat'] ?? ''));
        if ($vat !== '') {
            $v = (float) str_replace(',', '.', preg_replace('/[^\d.,]/', '', $vat));
            if ($v < 0 || $v > 100) $reasons[] = "Vat {$itemName} “{$vat}” không hợp lệ (0–100)";
            else $out['vat'] = round($v, 2);
        }
        foreach (['invoiceNo' => 'invoice_no', 'payer' => 'payer', 'note' => 'note'] as $k => $col) {
            $v = $this->str($f[$k] ?? null);
            if ($v !== null && $v !== '') $out[$col] = $v;
        }
        $raw = trim((string) ($f['dateRaw'] ?? ($f['date'] ?? '')));
        if ($raw !== '') {
            if (! $this->isValidDateStr($raw) && trim((string) ($f['date'] ?? '')) === '') $reasons[] = "Ngày HĐ {$itemName} “{$raw}” sai định dạng (cần dd/mm/yyyy)";
            elseif ($d = $this->cshtDate($f)) $out['date'] = $d;
        }
        return $out;
    }

    private function cshtGroupHasValue(array $f): bool
    {
        foreach (['amount', 'vat', 'invoiceNo', 'payer', 'date', 'dateRaw', 'note'] as $k) if (trim((string) ($f[$k] ?? '')) !== '') return true;
        return false;
    }

    /**
     * Các trường yêu cầu KHÁC giá trị hiện tại. 1 dòng: so từng ô. Nhiều dòng: tiền so TỔNG, ô khác chỉ coi là
     * "giống" khi mọi dòng cùng giá trị đó. Không có dòng: mọi trường đều là thay đổi.
     */
    private function cshtDiff(Collection $lines, array $fields): array
    {
        if ($lines->isEmpty()) return $fields;
        $diff = [];
        foreach ($fields as $k => $v) {
            if ($k === 'amount') {
                $cur = (int) $lines->sum(fn ($c) => (int) round((float) $c->amount));
                if ($cur !== (int) $v) $diff[$k] = $v;
                continue;
            }
            $vals = $lines->map(function ($c) use ($k) {
                if ($k === 'date') return $this->outDate($c->date);
                if ($k === 'vat') return (string) round((float) $c->vat, 2);
                return trim((string) ($c->{$k} ?? ''));
            })->unique()->values();
            $want = $k === 'vat' ? (string) round((float) $v, 2) : (string) $v;
            if ($vals->count() !== 1 || (string) $vals->first() !== $want) $diff[$k] = $v;
        }
        return $diff;
    }

    /** "a, b; c" → ['a','b','c'] (giữ thứ tự, bỏ trùng không phân biệt hoa thường). */
    private function cshtTags(string $raw): array
    {
        $out = []; $seen = [];
        foreach (preg_split('/\s*[,;\n]\s*/u', $raw) ?: [] as $t) {
            $t = trim($t);
            if ($t === '' || isset($seen[mb_strtolower($t)])) continue;
            $seen[mb_strtolower($t)] = true;
            $out[] = $t;
        }
        return $out;
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

    /**
     * Tiêu đề cột / nhãn nhóm → khoản trong danh mục. Khớp đúng tên (bỏ dấu); bỏ tiền tố "phí"/"số tiền";
     * mẫu cũ "PHÍ CSHT" / "SỐ TIỀN THANH LÝ"; cuối cùng là tiền tố DUY NHẤT ("CHI HỘ" → "Chi hộ LCC").
     */
    private function cshtItemForHeader(string $h, Collection $catalog): ?TruckingCostItem
    {
        $n = $this->cshtNorm(str_replace('*', '', $h));
        if ($n === '') return null;
        $byNorm = $catalog->keyBy(fn ($c) => $this->cshtNorm($c->name));
        $stripped = preg_replace('/^(sotien|phi|tien)/', '', $n);
        foreach ([$n, $stripped] as $k) {
            if ($k !== '' && $byNorm->has($k)) return $byNorm->get($k);
        }
        if (str_contains($n, 'csht')) return $byNorm->get($this->cshtNorm(self::CSHT_ITEM));
        if (str_contains($n, 'thanhli')) return $byNorm->get($this->cshtNorm(self::THANHLY_ITEM));
        if (strlen($n) >= 4) {
            $pref = $byNorm->filter(fn ($c, $k) => str_starts_with($k, $n));
            if ($pref->count() === 1) return $pref->first();
        }
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
