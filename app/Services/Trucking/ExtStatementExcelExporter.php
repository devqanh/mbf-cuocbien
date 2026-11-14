<?php

namespace App\Services\Trucking;

use App\Models\TruckingExtStatement;
use App\Models\TruckingLocation;
use App\Models\TruckingShipment;
use App\Models\TruckingWarehouse;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Xuất BẢNG KÊ XE NGOÀI ra Excel theo MẪU KẾ TOÁN "Bảng kê cước chi hộ" (BẢNG KÊ CHI TIẾT HÀNG HÓA VẬN CHUYỂN):
 * nạp storage/app/trucking/ext-statement-template.xlsx (giữ định dạng), điền Bên A (Cài đặt hệ thống),
 * Bên B = tên nhà xe (địa chỉ / MST / đại diện để trống điền tay), mỗi lô 1 dòng từ dòng 20.
 * Cước + chi hộ lấy theo SNAPSHOT bảng kê; chi hộ chia cột Nâng / Hạ / Phí khác theo TÊN khoản.
 * Tuyến đường / số cont / loại cont lấy theo lô hiện tại (snapshot không tách riêng), thiếu lô thì dùng snapshot.
 */
class ExtStatementExcelExporter
{
    private const START = 20;   // dòng dữ liệu đầu tiên của mẫu (mẫu có đúng 1 dòng, tổng ở dòng 21)

    public function download(TruckingExtStatement $st, array $seller): StreamedResponse
    {
        $tplPath = storage_path('app/trucking/ext-statement-template.xlsx');
        abort_unless(is_file($tplPath), 404, 'Thiếu file mẫu bảng kê xe ngoài.');
        $ss = IOFactory::load($tplPath);
        $sh = $ss->getActiveSheet();

        $dmy = fn ($d) => $d ? $d->format('d/m/Y') : '';
        $month = $st->period_to ?: $st->date ?: now();
        $sh->setCellValue('A4', 'BẢNG KÊ CHI TIẾT HÀNG HÓA VẬN CHUYỂN THÁNG ' . $month->format('m/Y'));
        $sh->setCellValue('A5', '(Số: ' . $st->no . ')');

        // BÊN A — Cài đặt hệ thống; chưa khai đại diện thì giữ dòng đại diện của mẫu.
        $sh->setCellValue('A9', 'BÊN A: ' . $seller['name']);
        $sh->setCellValue('A10', 'Địa chỉ: ' . $seller['address']);
        $sh->setCellValue('A11', 'MST: ' . $seller['tax']);
        if (trim($seller['rep']) !== '') {
            $sh->setCellValue('A12', 'Đại diện: ' . $seller['rep'] . str_repeat(' ', 30) . 'Chức vụ: ' . $seller['title']);
        }
        // BÊN B — nhà xe (thông tin pháp lý để trống cho kế toán điền tay).
        $sh->setCellValue('A13', 'BÊN B: ' . ($st->ext_vendor ?? ''));
        $sh->setCellValue('A14', 'Địa chỉ: ');
        $sh->setCellValue('A15', 'MST: ');
        $sh->setCellValue('A16', 'Đại diện: ' . str_repeat(' ', 40) . 'Chức vụ: ');

        $lines = $st->lines->values();
        $ships = TruckingShipment::with('warehouses')->whereIn('id', $lines->pluck('shipment_id')->filter()->all())->get()->keyBy('id');
        [$locN, $whN] = $this->nameMaps();

        $M = max($lines->count(), 1);
        $delta = $M - 1;
        $start = self::START;
        if ($delta > 0) {
            $sh->insertNewRowBefore($start + 1, $delta);
            $h = $sh->getRowDimension($start)->getRowHeight();
            // insertNewRowBefore đã chép kiểu từng ô của dòng phía trên (viền, định dạng số, căn lề) — chỉ cần chiều cao.
            for ($r = $start + 1; $r <= $start + $delta; $r++) $sh->getRowDimension($r)->setRowHeight($h);
        }
        $last = $start + $M - 1;
        foreach (['G', 'H', 'I', 'J'] as $c) $sh->getCell("{$c}{$start}")->setValueExplicit(null, DataType::TYPE_NULL);   // xóa số tiền ví dụ của mẫu (cột khác luôn được ghi đè)

        $rates = [];
        $sumVat = 0;
        foreach ($lines as $i => $l) {
            $r = $start + $i;
            $s = $l->shipment_id ? $ships->get($l->shipment_id) : null;

            $kho = $s ? ($s->warehouses->pluck('name')->filter()->all() ?: preg_split('/\s*,\s*/u', (string) $s->kho, -1, PREG_SPLIT_NO_EMPTY)) : [];
            $route = implode('-', array_filter([
                $locN($s->from_loc ?? $l->from_loc),
                ...array_map($whN, $kho),
                $locN($s->to_loc ?? $l->to_loc),
            ], fn ($p) => $p !== ''));
            [$contNo, $contType] = $s ? [(string) $s->cont_no, (string) $s->cont_type] : $this->splitCont((string) $l->cont_label);

            // Chi hộ theo tên khoản: "Nâng…" → Nâng, "Hạ…" → Hạ, còn lại cộng Phí khác + ghi tên vào Ghi chú.
            $nang = $ha = $khac = 0; $khacNames = [];
            foreach ($this->chohoItems($l) as $it) {
                $name = mb_strtolower(trim($it['item']));
                if (str_starts_with($name, 'nâng')) $nang += $it['amount'];
                elseif (str_starts_with($name, 'hạ')) $ha += $it['amount'];
                else { $khac += $it['amount']; $khacNames[] = trim($it['item']) . ' ' . number_format($it['amount'], 0, ',', '.'); }
            }
            $fee = (int) round((float) $l->fee);
            $rate = (float) $l->vat_rate;
            $rates[(string) $rate] = true;
            $sumVat += (int) round($fee * $rate / 100);
            $note = implode('; ', array_filter([trim((string) $l->note), implode(', ', $khacNames)]));

            $sh->setCellValue("A{$r}", $i + 1);
            $sh->setCellValueExplicit("B{$r}", $dmy($l->date), DataType::TYPE_STRING);
            $sh->setCellValueExplicit("C{$r}", (string) $l->bks, DataType::TYPE_STRING);
            $sh->setCellValue("D{$r}", $route);
            $sh->setCellValueExplicit("E{$r}", $contNo, DataType::TYPE_STRING);
            $sh->setCellValue("F{$r}", $contType);
            if ($fee) $sh->setCellValue("G{$r}", $fee);
            if ($nang) $sh->setCellValue("H{$r}", $nang);
            if ($ha) $sh->setCellValue("I{$r}", $ha);
            if ($khac) $sh->setCellValue("J{$r}", $khac);
            $sh->setCellValue("K{$r}", "=H{$r}+I{$r}+J{$r}");
            $sh->setCellValue("L{$r}", $note);
        }

        // Tổng + VAT + cộng cước + chi hộ + tổng phải trả (vị trí mẫu dời theo số dòng chèn).
        $tot = $last + 1;
        $sh->setCellValue("G{$tot}", "=SUM(G{$start}:G{$last})");
        $sh->setCellValue("K{$tot}", "=SUM(K{$start}:K{$last})");
        $vatRow = $tot + 1;
        $pct = fn ($r) => rtrim(rtrim(number_format((float) $r, 2, '.', ''), '0'), '.') . '%';
        $sumFee = (int) $lines->sum(fn ($l) => (int) round((float) $l->fee));
        $one = count($rates) === 1 ? (float) array_key_first($rates) : null;
        if ($one !== null && (int) round($sumFee * $one / 100) === $sumVat) {   // cùng 1 mức VAT như mẫu → công thức (khớp tổng bảng kê)
            $sh->setCellValue("E{$vatRow}", 'VAT ' . $pct($one) . ':');
            $sh->setCellValue("G{$vatRow}", "=ROUND(G{$tot}*{$one}%,0)");
        } else {                     // nhiều mức VAT theo lô → cộng VAT từng lô (làm tròn từng lô như bảng kê)
            $sh->setCellValue("E{$vatRow}", 'VAT ' . implode('/', array_map($pct, array_keys($rates))) . ':');
            $sh->setCellValue("G{$vatRow}", $sumVat);
        }
        $sh->setCellValue('G' . ($tot + 2), "=G{$tot}+G{$vatRow}");
        $sh->setCellValue('G' . ($tot + 3), "=K{$tot}");
        $sh->setCellValue('G' . ($tot + 4), '=G' . ($tot + 2) . '+G' . ($tot + 3));

        $d = $st->date ?: now();
        $sh->setCellValue('K' . (28 + $delta), 'Ngày ' . $d->format('d') . ' tháng ' . $d->format('m') . ' năm ' . $d->format('Y'));

        $sh->setSelectedCells('A1');
        $writer = new XlsxWriter($ss);
        $filename = 'bang-ke-xe-ngoai-' . preg_replace('/[^\w\-]+/u', '-', (string) $st->no) . '.xlsx';
        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /** Mã/tên → TÊN địa điểm & kho; ký hiệu dùng chung nhiều địa điểm (vd HPP) giữ nguyên mã. */
    private function nameMaps(): array
    {
        $locs = TruckingLocation::get(['name', 'code']);
        $perCode = $locs->filter(fn ($x) => $x->code && $x->name)->countBy('code');
        $loc = [];
        foreach ($locs as $x) {
            if ($x->name) $loc[$x->name] = $x->name;
            if ($x->code && ($perCode[$x->code] ?? 0) === 1) $loc[$x->code] = $x->name;
        }
        $wh = [];
        foreach (TruckingWarehouse::get(['name', 'code']) as $x) {
            if ($x->name) $wh[$x->name] = $x->name;
            if ($x->code) $wh[$x->code] = $x->name;
        }
        $f = fn (array $map) => fn ($v) => ($v = trim((string) $v)) !== '' ? ($map[$v] ?? $v) : '';
        return [$f($loc), $f($wh)];
    }

    /** "GAOU2126581 · 20DC" → [số cont, loại]; "2 × 40HC" (HPH) → ['', '2 × 40HC']. */
    private function splitCont(string $label): array
    {
        $p = array_map('trim', explode('·', $label));
        return count($p) >= 2 ? [$p[0], $p[1]] : ['', $label];
    }

    /** Khoản chi hộ của dòng (snapshot); dòng cũ không có chi tiết → 1 khoản theo ghi chú chi hộ. */
    private function chohoItems($l): array
    {
        $items = is_array($l->choho_items) ? $l->choho_items : [];
        $out = [];
        foreach ($items as $it) {
            $a = (int) round((float) ($it['amount'] ?? 0));
            if ($a) $out[] = ['item' => (string) ($it['item'] ?? ''), 'amount' => $a];
        }
        $choho = (int) round((float) $l->choho);
        return $out ?: ($choho ? [['item' => (string) ($l->choho_note ?: 'Chi hộ'), 'amount' => $choho]] : []);
    }
}
