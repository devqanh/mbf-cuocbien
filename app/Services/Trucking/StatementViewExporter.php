<?php

namespace App\Services\Trucking;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as XlDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Xuất "DỮ LIỆU ĐANG XEM" của 1 bảng kê (trang xem) ra Excel để tra cứu nội bộ — KHÁC mẫu chính thức gửi khách
 * (StatementExcelExporter). Dòng + số tiền do trang xem gửi lên (đúng bộ lọc + sửa chưa lưu đang hiện); file chỉ
 * dựng lại, không ghi gì vào DB.
 *  - Sheet "Bảng kê": 1 dòng/lô · nhóm cột Thông tin lô / Thu khách / Chi phí lô hàng (MỖI KHOẢN 1 CỘT) / Ghi chú;
 *    dòng TỔNG ở trên dùng SUBTOTAL → tự cộng lại theo bộ lọc Excel; lọc sẵn + cố định tiêu đề; tô màu trạng thái giá,
 *    ô chi hộ (xanh) / chi phí công ty (cam).
 *  - Sheet "Chi phí từng khoản": 1 dòng/khoản chi phí (số HĐ, người chi, ngày, ghi chú) để lọc / pivot.
 *  - Sheet "Tổng hợp khoản": mỗi khoản bao nhiêu lô, tổng tiền, chi hộ / công ty.
 */
class StatementViewExporter
{
    private const MONEY = '#,##0;-#,##0;"-"';
    private const C_INFO = '334155';   // nhóm Thông tin lô
    private const C_THU = '1D4ED8';    // nhóm Thu khách
    private const C_COST = 'B45309';   // nhóm Chi phí lô hàng
    private const C_NOTE = '6D28D9';   // nhóm Ghi chú
    private const F_CHOHO = 'DCFCE7';  // ô khoản CHI HỘ (thu lại khách)
    private const F_CO = 'FFEDD5';     // ô khoản CHI PHÍ CÔNG TY
    private const F_ZEBRA = 'F8FAFC';

    /**
     * @param array $head  thông tin bảng kê lấy từ DB: no, customer, from, to, vatRate, date
     * @param array $rows  dòng đang hiện (trang xem gửi lên)
     * @param array $extraCols cột chi phí riêng [{key,label,vat}]
     * @param array $filters mô tả bộ lọc đang bật (chuỗi)
     */
    public function download(array $head, array $rows, array $extraCols, array $filters): StreamedResponse
    {
        $ss = new Spreadsheet();
        $ss->getDefaultStyle()->getFont()->setName('Arial')->setSize(10);
        $items = $this->costItems($rows);

        $this->mainSheet($ss->getActiveSheet(), $head, $rows, $extraCols, $filters, $items);
        $this->costSheet($ss->createSheet(), $rows);
        $this->summarySheet($ss->createSheet(), $rows, $items);
        $ss->setActiveSheetIndex(0);

        $writer = new XlsxWriter($ss);
        $filename = 'bang-ke-' . preg_replace('/[^\w\-]+/u', '-', (string) ($head['no'] ?: 'export')) . '-du-lieu-' . now('Asia/Ho_Chi_Minh')->format('Ymd-Hi') . '.xlsx';

        return response()->streamDownload(fn () => $writer->save('php://output'), $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }

    /** Các khoản chi phí xuất hiện trong các lô (gộp không phân biệt hoa/thường), khoản phổ biến trước. */
    private function costItems(array $rows): array
    {
        $cnt = []; $name = [];
        foreach ($rows as $r) {
            $seen = [];
            foreach ((array) ($r['costs'] ?? []) as $c) {
                $it = trim((string) ($c['item'] ?? '')); if ($it === '') continue;
                $k = mb_strtolower($it); $name[$k] ??= $it;
                if (! isset($seen[$k])) { $cnt[$k] = ($cnt[$k] ?? 0) + 1; $seen[$k] = true; }
            }
        }
        uksort($cnt, fn ($a, $b) => [$cnt[$b], $name[$a]] <=> [$cnt[$a], $name[$b]]);
        return array_map(fn ($k) => ['key' => $k, 'name' => $name[$k], 'lots' => $cnt[$k]], array_keys($cnt));
    }

    private function mainSheet(Worksheet $ws, array $head, array $rows, array $extraCols, array $filters, array $items): void
    {
        $ws->setTitle('Bảng kê');
        // [nhãn, độ rộng, kiểu, nhóm, lấy giá trị]
        $cols = [
            ['STT', 5, 'int', 'info', fn ($r, $i) => $i + 1],
            ['Booking / Lô', 16, 'text', 'info', fn ($r) => $r['booking'] ?? ''],
            ['Loại', 7, 'text', 'info', fn ($r) => $r['io'] ?? ''],
            ['Số cont', 14, 'text', 'info', fn ($r) => $r['contNo'] ?? ''],
            ['Loại cont', 9, 'text', 'info', fn ($r) => $r['contType'] ?? ''],
            ['Số tờ khai', 14, 'text', 'info', fn ($r) => $r['declNo'] ?? ''],
            ['Biển số', 11, 'text', 'info', fn ($r) => $r['bks'] ?? ''],
            ['Nơi lấy', 18, 'text', 'info', fn ($r) => $r['from'] ?? ''],
            ['Nơi hạ', 18, 'text', 'info', fn ($r) => $r['to'] ?? ''],
            ['Tuyến tính giá', 34, 'text', 'info', fn ($r) => $r['route'] ?? ''],
            ['Connect', 11, 'text', 'info', fn ($r) => $r['conn'] ?? ''],
            ['Giờ kéo vào', 15, 'dt', 'info', fn ($r) => $r['gioVao'] ?? ''],
            ['Giờ ra', 15, 'dt', 'info', fn ($r) => $r['gioRa'] ?? ''],
            ['Trạng thái giá', 14, 'status', 'info', fn ($r) => $r['status'] ?? ''],
            ['Doanh thu (cước+dầu)', 14, 'money', 'thu', fn ($r) => $r['base'] ?? 0],
            ['VAT %', 7, 'pct', 'thu', fn ($r) => $r['vatRate'] ?? 0],
            ['VAT', 12, 'money', 'thu', fn ($r) => $r['vat'] ?? 0],
        ];
        foreach ($extraCols as $c) {
            $key = (string) ($c['key'] ?? '');
            $cols[] = [trim((string) ($c['label'] ?? '')) . (! empty($c['vat']) ? ' (+VAT)' : ''), 13, 'money', 'thu', fn ($r) => $r['extras'][$key] ?? 0];
        }
        array_push($cols,
            ['Chi hộ', 12, 'money', 'thu', fn ($r) => $r['choho'] ?? 0],
            ['Phí tờ khai', 12, 'money', 'thu', fn ($r) => $r['tk'] ?? 0],
            ['TỔNG PHẢI THU', 15, 'total', 'thu', fn ($r) => $r['total'] ?? 0],
        );
        foreach ($items as $it) {
            $k = $it['key'];
            $cols[] = [$it['name'], max(10, min(18, mb_strlen($it['name']) + 3)), 'cost', 'cost', fn ($r) => $this->costCell($r, $k)];
            // Cột SỐ HÓA ĐƠN ngay sau cột tiền của khoản (chỉ thêm khi khoản có ít nhất 1 số HĐ) — nhiều HĐ cách dấu phẩy.
            $inv = fn ($r) => implode(', ', array_unique(array_filter(array_map(
                fn ($c) => mb_strtolower(trim((string) ($c['item'] ?? ''))) === $k ? trim((string) ($c['invoiceNo'] ?? '')) : '', (array) ($r['costs'] ?? [])))));
            if (array_filter($rows, fn ($r) => $inv($r) !== '')) $cols[] = [$it['name'] . ' · Số HĐ', 14, 'inv', 'cost', $inv];
        }
        array_push($cols,
            ['Tổng chi phí lô', 14, 'money', 'cost', fn ($r) => array_sum(array_map(fn ($c) => (int) ($c['amount'] ?? 0), (array) ($r['costs'] ?? [])))],
            ['Trong đó chi hộ', 13, 'money', 'cost', fn ($r) => array_sum(array_map(fn ($c) => ! empty($c['billable']) ? (int) ($c['amount'] ?? 0) : 0, (array) ($r['costs'] ?? [])))],
            ['Ghi chú lô', 30, 'wrap', 'note', fn ($r) => $r['infoNote'] ?? ''],
            ['Ghi chú bảng kê', 26, 'wrap', 'note', fn ($r) => $r['userNote'] ?? ''],
            ['Ghi chú chi phí', 30, 'wrap', 'note', fn ($r) => implode("\n", array_filter(array_map(
                fn ($c) => trim((string) ($c['note'] ?? '')) !== '' ? trim((string) $c['item']) . ': ' . trim((string) $c['note']) : '', (array) ($r['costs'] ?? [])))) ],
        );

        $n = count($cols); $last = Coordinate::stringFromColumnIndex($n);
        $HDR_GROUP = 4; $HDR = 5; $FIRST = 6; $END = max($FIRST, $FIRST + count($rows) - 1);

        // Tiêu đề + mô tả
        $ws->setCellValue('A1', 'BẢNG KÊ ' . ($head['no'] ?? '') . ' — ' . ($head['customer'] ?? ''));
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $period = trim(($head['from'] ? $this->vnDate($head['from']) : '…') . ' → ' . ($head['to'] ? $this->vnDate($head['to']) : '…'));
        $ws->setCellValue('A2', 'Cont ra: ' . $period . ' · VAT mặc định ' . ($head['vatRate'] ?? 0) . '% · ' . count($rows) . ' lô'
            . ($filters ? ' · Đang lọc: ' . implode(' · ', $filters) : '') . ' · Xuất lúc ' . now('Asia/Ho_Chi_Minh')->format('d/m/Y H:i')
            . ' · Màu ô chi phí: xanh = chi hộ (thu lại khách), cam = chi phí công ty');
        $ws->getStyle('A2')->getFont()->setItalic(true)->setSize(9)->getColor()->setRGB('64748B');

        // Nhóm cột (dòng 4, gộp ô) + tiêu đề cột (dòng 5)
        $groupLabel = ['info' => 'THÔNG TIN LÔ', 'thu' => 'DOANH THU · PHẢI THU KHÁCH', 'cost' => 'CHI PHÍ LÔ HÀNG (theo khoản)', 'note' => 'GHI CHÚ'];
        $groupColor = ['info' => self::C_INFO, 'thu' => self::C_THU, 'cost' => self::C_COST, 'note' => self::C_NOTE];
        $start = 1;
        for ($i = 1; $i <= $n; $i++) {
            $g = $cols[$i - 1][3];
            if ($i === $n || $cols[$i][3] !== $g) {
                $a = Coordinate::stringFromColumnIndex($start); $b = Coordinate::stringFromColumnIndex($i);
                $ws->setCellValue($a . $HDR_GROUP, $groupLabel[$g]);
                if ($start < $i) $ws->mergeCells("{$a}{$HDR_GROUP}:{$b}{$HDR_GROUP}");
                $this->fill($ws, "{$a}{$HDR_GROUP}:{$b}{$HDR_GROUP}", $groupColor[$g]);
                $ws->getStyle("{$a}{$HDR_GROUP}:{$b}{$HDR_GROUP}")->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $ws->getStyle("{$a}{$HDR_GROUP}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Tiêu đề cột: nền nhạt cùng tông nhóm
                $this->fill($ws, "{$a}{$HDR}:{$b}{$HDR}", $this->tint($groupColor[$g]));
                $start = $i + 1;
            }
        }
        foreach ($cols as $i => [$label, $w]) {
            $c = Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue($c . $HDR, $label);
            $ws->getColumnDimension($c)->setWidth($w);
        }
        $ws->getStyle("A{$HDR}:{$last}{$HDR}")->getFont()->setBold(true);
        $ws->getStyle("A{$HDR}:{$last}{$HDR}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_CENTER)->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getRowDimension($HDR)->setRowHeight(32);

        // Dữ liệu
        foreach (array_values($rows) as $i => $r) {
            $row = $FIRST + $i;
            if ($i % 2) $ws->getStyle("A{$row}:{$last}{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB(self::F_ZEBRA);
            foreach ($cols as $j => [, , $type, , $get]) {
                $c = Coordinate::stringFromColumnIndex($j + 1) . $row;
                $v = $get($r, $i);
                switch ($type) {
                    case 'money': case 'total':
                        $ws->setCellValueExplicit($c, (float) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        break;
                    case 'cost':
                        [$amt, $choho] = $v;
                        if ($amt === null) break;
                        $ws->setCellValueExplicit($c, (float) $amt, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_NUMERIC);
                        $ws->getStyle($c)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($choho ? self::F_CHOHO : self::F_CO);
                        break;
                    case 'pct': case 'int':
                        $ws->setCellValue($c, (float) $v);
                        break;
                    case 'dt':
                        $x = $this->excelDate((string) $v);
                        if ($x !== null) $ws->setCellValue($c, $x); else $ws->setCellValueExplicit($c, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                        break;
                    case 'status':
                        $ws->setCellValue($c, $v === 'manual' ? 'Giá tùy chỉnh' : ($v === 'matched' ? 'Bảng giá' : 'Chưa khớp giá'));
                        $ws->getStyle($c)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($v === 'manual' ? 'FEF3C7' : ($v === 'matched' ? 'DCFCE7' : 'FEE2E2'));
                        $ws->getStyle($c)->getFont()->setBold(true)->getColor()->setRGB($v === 'manual' ? '92400E' : ($v === 'matched' ? '166534' : 'B91C1C'));
                        break;
                    default:
                        $ws->setCellValueExplicit($c, (string) $v, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
            }
        }

        // Định dạng theo cột + dòng TỔNG (SUBTOTAL 109 → bỏ dòng bị ẩn khi lọc trong Excel)
        $ws->setCellValue('A3', 'TỔNG (tự cộng theo dòng đang lọc)');
        foreach ($cols as $j => [, , $type]) {
            $col = Coordinate::stringFromColumnIndex($j + 1);
            $rng = "{$col}{$FIRST}:{$col}{$END}";
            if (in_array($type, ['money', 'total', 'cost'], true)) {
                $ws->getStyle($rng)->getNumberFormat()->setFormatCode(self::MONEY);
                $ws->setCellValue("{$col}3", "=SUBTOTAL(109,{$rng})");
                $ws->getStyle("{$col}3")->getNumberFormat()->setFormatCode(self::MONEY);
            }
            if ($type === 'total') { $ws->getStyle($rng)->getFont()->setBold(true); $ws->getStyle($rng)->getFont()->getColor()->setRGB(self::C_THU); }
            if ($type === 'pct') $ws->getStyle($rng)->getNumberFormat()->setFormatCode('0"%"');
            if ($type === 'dt') { $ws->getStyle($rng)->getNumberFormat()->setFormatCode('dd/mm/yyyy hh:mm'); $ws->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER); }
            if (in_array($type, ['int', 'pct', 'status'], true)) $ws->getStyle($rng)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            if ($type === 'wrap') $ws->getStyle($rng)->getAlignment()->setWrapText(true);
            if ($type === 'inv') { $ws->getStyle($rng)->getFont()->getColor()->setRGB('475569'); $ws->getStyle($rng)->getAlignment()->setWrapText(true); }
        }
        $ws->getStyle("A3:{$last}3")->getFont()->setBold(true);
        $this->fill($ws, "A3:{$last}3", 'FEF9C3');
        $ws->getStyle("A{$FIRST}:{$last}{$END}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
        $ws->getStyle("A{$HDR_GROUP}:{$last}{$END}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');

        $ws->setAutoFilter("A{$HDR}:{$last}{$END}");
        $ws->freezePane('E' . $FIRST);   // giữ STT · Booking · Loại · Số cont + tiêu đề khi cuộn
        $this->print($ws);
    }

    /** [số tiền, có phải chi hộ] của 1 khoản trong 1 lô; null nếu lô không có khoản đó. */
    private function costCell(array $r, string $key): array
    {
        $sum = null; $choho = false;
        foreach ((array) ($r['costs'] ?? []) as $c) {
            if (mb_strtolower(trim((string) ($c['item'] ?? ''))) !== $key) continue;
            $sum = ($sum ?? 0) + (int) ($c['amount'] ?? 0);
            $choho = $choho || ! empty($c['billable']);
        }
        return [$sum, $choho];
    }

    private function costSheet(Worksheet $ws, array $rows): void
    {
        $ws->setTitle('Chi phí từng khoản');
        $head = ['STT', 'Booking / Lô', 'Loại', 'Số cont', 'Khoản', 'Số tiền', 'VAT %', 'Chi hộ', 'Số HĐ', 'Người chi', 'Ngày', 'Ghi chú'];
        $w = [5, 16, 7, 14, 22, 14, 7, 9, 14, 16, 11, 36];
        foreach ($head as $i => $h) {
            $c = Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$c}2", $h); $ws->getColumnDimension($c)->setWidth($w[$i]);
        }
        $this->fill($ws, 'A2:L2', $this->tint(self::C_COST));
        $ws->getStyle('A2:L2')->getFont()->setBold(true);
        $row = 3; $k = 0;
        foreach ($rows as $r) {
            foreach ((array) ($r['costs'] ?? []) as $c) {
                $bill = ! empty($c['billable']);
                $ws->setCellValue("A{$row}", ++$k);
                // Chữ (booking / số cont / số HĐ có thể toàn số) ghi dạng TEXT để Excel không đổi thành số.
                foreach (['B' => $r['booking'] ?? '', 'C' => $r['io'] ?? '', 'D' => $r['contNo'] ?? '', 'E' => $c['item'] ?? '', 'H' => $bill ? 'Chi hộ' : 'Công ty',
                    'I' => $c['invoiceNo'] ?? '', 'J' => $c['payer'] ?? '', 'L' => $c['note'] ?? ''] as $col => $txt) {
                    $ws->setCellValueExplicit("{$col}{$row}", (string) $txt, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
                }
                $ws->setCellValue("F{$row}", (float) ($c['amount'] ?? 0));
                $ws->setCellValue("G{$row}", (float) ($c['vat'] ?? 0));
                $d = $this->excelDate((string) ($c['date'] ?? ''));
                if ($d !== null) $ws->setCellValue("K{$row}", $d);
                $ws->getStyle("H{$row}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($bill ? self::F_CHOHO : self::F_CO);
                $row++;
            }
        }
        $end = max(3, $row - 1);
        $ws->setCellValue('A1', 'TỔNG (tự cộng theo dòng đang lọc)');
        $ws->setCellValue('F1', "=SUBTOTAL(109,F3:F{$end})");
        $ws->getStyle('A1:L1')->getFont()->setBold(true);
        $this->fill($ws, 'A1:L1', 'FEF9C3');
        $ws->getStyle("F1:F{$end}")->getNumberFormat()->setFormatCode(self::MONEY);
        $ws->getStyle("G3:G{$end}")->getNumberFormat()->setFormatCode('0"%"');
        $ws->getStyle("K3:K{$end}")->getNumberFormat()->setFormatCode('dd/mm/yyyy');
        $ws->getStyle("L3:L{$end}")->getAlignment()->setWrapText(true);
        $ws->getStyle("A2:L{$end}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        $ws->setAutoFilter("A2:L{$end}");
        $ws->freezePane('A3');
        $this->print($ws);
    }

    private function summarySheet(Worksheet $ws, array $rows, array $items): void
    {
        $ws->setTitle('Tổng hợp khoản');
        foreach (['Khoản', 'Số lô', 'Tổng tiền', 'Chi hộ (thu lại khách)', 'Chi phí công ty'] as $i => $h) {
            $c = Coordinate::stringFromColumnIndex($i + 1);
            $ws->setCellValue("{$c}1", $h); $ws->getColumnDimension($c)->setWidth([26, 9, 16, 20, 17][$i]);
        }
        $this->fill($ws, 'A1:E1', $this->tint(self::C_COST));
        $ws->getStyle('A1:E1')->getFont()->setBold(true);
        $row = 2;
        foreach ($items as $it) {
            $tot = 0; $ch = 0;
            foreach ($rows as $r) foreach ((array) ($r['costs'] ?? []) as $c) {
                if (mb_strtolower(trim((string) ($c['item'] ?? ''))) !== $it['key']) continue;
                $a = (int) ($c['amount'] ?? 0); $tot += $a; if (! empty($c['billable'])) $ch += $a;
            }
            $ws->fromArray([$it['name'], $it['lots'], $tot, $ch, $tot - $ch], null, "A{$row}", true);
            $row++;
        }
        $end = max(2, $row - 1);
        $ws->setCellValue("A{$row}", 'TỔNG');
        foreach (['C', 'D', 'E'] as $c) $ws->setCellValue("{$c}{$row}", "=SUM({$c}2:{$c}{$end})");
        $ws->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
        $this->fill($ws, "A{$row}:E{$row}", 'FEF9C3');
        $ws->getStyle("C2:E{$row}")->getNumberFormat()->setFormatCode(self::MONEY);
        $ws->getStyle("B2:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $ws->getStyle("A1:E{$row}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('E2E8F0');
        $ws->freezePane('A2');
    }

    private function fill(Worksheet $ws, string $range, string $rgb): void
    {
        $ws->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB($rgb);
    }

    /** Màu nhạt (pha trắng 85%) cho dòng tiêu đề cột cùng tông nhóm. */
    private function tint(string $rgb): string
    {
        return implode('', array_map(fn ($h) => sprintf('%02X', (int) round(hexdec($h) + (255 - hexdec($h)) * 0.85)), str_split($rgb, 2)));
    }

    private function excelDate(string $v): ?float
    {
        $v = trim($v); if ($v === '') return null;
        try { return XlDate::PHPToExcel(Carbon::parse($v)); } catch (\Throwable) { return null; }
    }

    private function vnDate(string $v): string
    {
        try { return Carbon::parse($v)->format('d/m/Y'); } catch (\Throwable) { return $v; }
    }

    private function print(Worksheet $ws): void
    {
        $ws->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setPaperSize(PageSetup::PAPERSIZE_A4)
            ->setFitToWidth(1)->setFitToHeight(0);
        $ws->getSheetView()->setZoomScale(90);
    }
}
