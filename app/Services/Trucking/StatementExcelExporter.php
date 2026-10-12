<?php

namespace App\Services\Trucking;

use App\Models\TruckingLocation;
use App\Models\TruckingWarehouse;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Xuất bảng kê ra Excel theo MẪU CHÍNH THỨC (STATEMENT ACCOUNT — XUẤT-HPH):
 * nạp file template (giữ nguyên định dạng), điền dữ liệu + giãn/thu số dòng.
 * XUẤT THEO SNAPSHOT đã lưu — KHÔNG đọc lô realtime.
 *
 * @param array $st     Bảng kê đã serialize (TruckingV2Service::statementToArray)
 * @param array $seller Thông tin bên bán (TruckingV2Service::sellerInfo)
 */
class StatementExcelExporter
{
    public function download(array $st, array $seller): StreamedResponse
    {
        $info = $st['info'] ?? [];

        $tplPath = storage_path('app/trucking/statement-template.xlsx');
        abort_unless(is_file($tplPath), 404, 'Thiếu file mẫu bảng kê.');
        $ss = IOFactory::load($tplPath);
        $sh = $ss->getActiveSheet();
        $sh->setAutoFilter('');   // phòng vệ: không để AutoFilter range thành #REF! sau khi giãn/thu dòng

        $dmy = fn (?string $iso) => $iso && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : (string) $iso;

        // BÊN MUA — tự lấy từ bảng kê (khách + địa chỉ + MST đã lưu theo khách)
        $sh->setCellValue('A8', 'BÊN MUA:  ' . ($st['customer'] ?? ''));
        $sh->setCellValue('A9', 'Địa chỉ: ' . ($info['address'] ?? ''));
        $sh->setCellValue('A10', 'MST: ' . ($info['taxCode'] ?? ''));
        if (trim((string) ($info['rep'] ?? '')) !== '')   $sh->setCellValue('A11', 'Đại diện: ' . $info['rep']);
        if (trim((string) ($info['title'] ?? '')) !== '') $sh->setCellValue('D11', 'Chức vụ: ' . $info['title']);

        // BÊN BÁN — tự điền từ Cài đặt hệ thống (không phải sửa template tay nữa)
        $sh->setCellValue('A13', 'BÊN BÁN: ' . $seller['name']);
        $sh->setCellValue('A14', 'Địa chỉ: ' . $seller['address']);
        $sh->setCellValue('A15', 'MST: ' . $seller['tax']);
        if (trim($seller['rep']) !== '')   $sh->setCellValue('A16', 'Đại diện: ' . $seller['rep']);
        if (trim($seller['title']) !== '') $sh->setCellValue('D16', 'Chức vụ: ' . $seller['title']);

        $sh->setCellValueExplicit('O18', (string) ($st['no'] ?? ''), DataType::TYPE_STRING);
        $sh->setCellValueExplicit('O19', $dmy($st['date'] ?? null), DataType::TYPE_STRING);
        // Hạn thanh toán (statementDueDate: info.dueDate, chưa đặt thì ngày lập + Hạn TT của khách) — ô trống ngay dưới Date của mẫu.
        $due = (string) ($st['dueDate'] ?? '');
        if ($due !== '') {
            $sh->setCellValue('N20', 'Due date');
            $sh->setCellValueExplicit('O20', $dmy($due), DataType::TYPE_STRING);
        }

        // Chỉ lô trong PHẠM VI Nhập/Xuất của bảng kê (ioScope) — lô khác loại không tính, không in ra Excel.
        $scope    = $st['ioScope'] ?? 'all';
        $lines    = array_values(array_filter($st['lines'] ?? [], fn ($l) => \App\Services\TruckingV2Service::ioInScope($l['io'] ?? '', $scope)));
        $M        = max(count($lines), 1);     // luôn giữ ≥1 dòng vùng dữ liệu
        $start    = 22;
        $tplRows  = 46;                         // mẫu có 46 dòng (22..67)
        $delta    = $M - $tplRows;

        // CỘT CHI PHÍ RIÊNG của bảng kê (extraCols [{key,label,vat}], số từng lô ở detail.extras[key]).
        // Mẫu có sẵn 3 cột phí K "Lưu ca", L "Tờ khai không phơi", M "Phí tái sử dụng container rỗng":
        // cột riêng TRÙNG TÊN (so khớp chuẩn hóa) điền thẳng vào cột mẫu; cột khác CHÈN THÊM trước
        // "Tổng tiền" (O) — nhãn ở dòng tiêu đề 21, kiểu kế thừa cột N. Chèn cột TRƯỚC khi giãn/thu dòng.
        $extraCols = array_values(array_filter($st['extraCols'] ?? [], fn ($c) => is_array($c) && trim((string) ($c['label'] ?? '')) !== ''));
        $norm   = fn ($s) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $s)));
        $tplFee = [];                           // [nhãn chuẩn hóa => cột mẫu]
        foreach (['K', 'L', 'M'] as $c) { $h = $norm($sh->getCell($c . '21')->getValue()); if ($h !== '') $tplFee[$h] = $c; }
        $colOf = []; $toInsert = [];            // key cột riêng => chữ cột Excel
        foreach ($extraCols as $c) {
            $h = $norm($c['label']);
            if (isset($tplFee[$h]) && ! in_array($tplFee[$h], $colOf, true)) $colOf[$c['key']] = $tplFee[$h];
            else $toInsert[] = $c;
        }
        $nIns = count($toInsert);
        if ($nIns > 0) {
            $sh->insertNewColumnBefore('O', $nIns);
            foreach ($toInsert as $i => $c) {
                $letter = Coordinate::stringFromColumnIndex(15 + $i);   // O, P, … (vị trí cũ của O trở đi)
                $colOf[$c['key']] = $letter;
                $sh->getColumnDimension($letter)->setWidth($sh->getColumnDimension('N')->getWidth());
                $sh->setCellValue($letter . '21', $c['label']);
            }
        }
        $colTong = Coordinate::stringFromColumnIndex(15 + $nIns);   // "Tổng tiền" (O khi không chèn)
        $colNote = Coordinate::stringFromColumnIndex(16 + $nIns);   // "Ghi chú"  (P khi không chèn)

        // Map mã/tên → TÊN địa điểm & kho (chỉ là từ điển hiển thị tên, không phải dữ liệu lô)
        $locMap = [];
        foreach (TruckingLocation::get(['name', 'code']) as $x) {
            if ($x->name) $locMap[$x->name] = $x->name;
            if ($x->code) $locMap[$x->code] = $x->name;
        }
        $whMap = [];
        foreach (TruckingWarehouse::get(['name', 'code']) as $x) {
            if ($x->name) $whMap[$x->name] = $x->name;
            if ($x->code) $whMap[$x->code] = $x->name;
        }
        $locN = fn ($v) => ($v = trim((string) $v)) !== '' ? ($locMap[$v] ?? $v) : '';

        if ($delta < 0) {
            $sh->removeRow($start + 1, -$delta);                // bỏ bớt từ dòng 23
        } elseif ($delta > 0) {
            $sh->insertNewRowBefore($start + 1, $delta);        // chèn thêm sau dòng 22
            $h = $sh->getRowDimension($start)->getRowHeight();
            for ($r = $start + 1; $r <= $start + $delta; $r++) {
                $sh->duplicateStyle($sh->getStyle("A{$start}:{$colNote}{$start}"), "A{$r}:{$colNote}{$r}");
                $sh->getRowDimension($r)->setRowHeight($h);
            }
        }
        // Cột chèn thêm: kế thừa kiểu (viền, font, căn, định dạng số) của cột N từ tiêu đề tới dòng Tổng.
        if ($nIns > 0) {
            for ($r = 20; $r <= $start + $M; $r++) {
                for ($i = 0; $i < $nIns; $i++) $sh->duplicateStyle($sh->getStyle("N{$r}"), Coordinate::stringFromColumnIndex(15 + $i) . $r);
            }
        }

        $sumCuoc = 0; $sumThanhLy = 0; $sumTong = 0; $sumExtra = array_fill_keys(array_keys($colOf), 0);
        foreach ($lines as $i => $l) {
            $r = $start + $i;

            $declNo   = $l['declNo']   ?? '';
            $contType = $l['contType'] ?? '';
            $inv      = $l['inv']      ?? '';
            $contNo   = $l['contNo']   ?? '';
            $bks      = $l['bks']      ?? '';
            // Cột Note: ghi chú người dùng gõ ở bảng kê; chưa gõ thì ghi chú tự sinh như cũ (ghi chú kế toán / tuyến + kết nối).
            $note     = trim((string) ($l['userNote'] ?? '')) ?: ($l['note'] ?? '');

            // Tuyến vận chuyển — lấy từ snapshot (detail.route đã chốt khi lưu)
            $segName = function ($v) use ($locMap, $whMap) {
                $v = trim((string) $v);
                if ($v === '' || $v === '?') return '';
                return $locMap[$v] ?? $whMap[$v] ?? $v;
            };
            $snapRoute = trim((string) (($l['detail']['route'] ?? '')));
            if ($snapRoute !== '') {
                $segs  = array_filter(array_map($segName, preg_split('/\s*→\s*/u', $snapRoute)), fn ($p) => $p !== '');
                $route = implode(' → ', $segs);
            } else {
                $parts = [];
                if (trim((string) ($l['from'] ?? '')) !== '') $parts[] = $locN($l['from']);
                if (trim((string) ($l['to'] ?? '')) !== '')   $parts[] = $locN($l['to']);
                $route = implode(' → ', array_filter($parts, fn ($p) => trim((string) $p) !== ''));
            }

            $thanhLy = (int) ($l['thanhLy'] ?? 0);
            $cuoc    = (int) (($l['cuoc'] ?? 0) ?: ($l['phaiThu'] ?? 0));
            // Phí riêng từng cột của lô (detail.extras[key]) — cộng vào Tổng tiền dòng như phí thanh lý.
            $extras = []; $extraSum = 0;
            foreach ($colOf as $key => $letter) {
                $v = (int) round((float) ($l['detail']['extras'][$key] ?? 0));
                $extras[$letter] = $v; $extraSum += $v; $sumExtra[$key] += $v;
            }
            $tong = $cuoc + $thanhLy + $extraSum;
            $sumCuoc += $cuoc; $sumThanhLy += $thanhLy; $sumTong += $tong;

            $sh->setCellValue("A{$r}", $i + 1);
            $sh->setCellValue("B{$r}", $route);
            $sh->setCellValueExplicit("C{$r}", $dmy($l['date'] ?? null), DataType::TYPE_STRING);
            $sh->setCellValueExplicit("D{$r}", (string) ($l['booking'] ?? ''), DataType::TYPE_STRING);
            $sh->setCellValueExplicit("E{$r}", (string) $declNo, DataType::TYPE_STRING);
            $sh->setCellValue("F{$r}", $contType);
            $sh->setCellValueExplicit("G{$r}", (string) $inv, DataType::TYPE_STRING);
            $sh->setCellValueExplicit("H{$r}", (string) $contNo, DataType::TYPE_STRING);
            $sh->setCellValueExplicit("I{$r}", (string) $bks, DataType::TYPE_STRING);
            $sh->setCellValue("J{$r}", $cuoc);
            $sh->setCellValue("K{$r}", 0);
            $sh->setCellValue("L{$r}", 0);
            $sh->setCellValue("M{$r}", 0);
            foreach ($extras as $letter => $v) $sh->setCellValue("{$letter}{$r}", $v);   // ghi đè K/L/M nếu cột riêng trùng tên
            $sh->setCellValue("N{$r}", $thanhLy);
            $sh->setCellValue("{$colTong}{$r}", $tong);          // số cụ thể, không dùng SUM
            $sh->setCellValue("{$colNote}{$r}", $note);
        }

        // Dòng Tổng — điền SỐ CỤ THỂ (không dùng công thức SUM)
        $totalRow = $start + $M;
        $sh->setCellValue("J{$totalRow}", $sumCuoc);
        $sh->setCellValue("K{$totalRow}", 0);
        $sh->setCellValue("L{$totalRow}", 0);
        $sh->setCellValue("M{$totalRow}", 0);
        foreach ($colOf as $key => $letter) $sh->setCellValue("{$letter}{$totalRow}", $sumExtra[$key]);
        $sh->setCellValue("N{$totalRow}", $sumThanhLy);
        $sh->setCellValue("{$colTong}{$totalRow}", $sumTong);

        // Ngày tháng (J70 đã merge J:P) dời theo delta
        $d = explode('-', (string) ($st['date'] ?? ''));
        if (count($d) === 3) {
            $sh->setCellValue('J' . (70 + $delta), "Ngày {$d[2]} tháng {$d[1]} năm {$d[0]}");
        }

        // Reset vị trí cuộn/ô chọn về đầu (template lưu sẵn topLeftCell=A67 → mở file bị cuộn xuống)
        $sh->freezePane('A22', 'A22');
        $sh->setSelectedCells('A1');
        $ss->setActiveSheetIndex($ss->getIndex($sh));

        $writer   = new XlsxWriter($ss);
        $filename = 'bang-ke-' . preg_replace('/[^\w\-]+/u', '-', (string) ($st['no'] ?? 'export')) . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }
}
