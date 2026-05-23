<?php

namespace App\Services;

use App\Models\Shipment;
use App\Models\User;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Generate XLSX export cho shipments của 1 period.
 *
 * 2 sheets (HÀNG NHẬP + HÀNG XUẤT), header với group colors, format date/currency,
 * filter ẩn cột theo user permissions.
 */
class ShipmentExportService
{
    /** Group color theo config/shipment_columns.php */
    private const GROUP_COLORS = [
        1 => 'D4E6B5',   // xanh lá pastel
        2 => 'FCE4D6',   // cam đào nhạt
        3 => 'DEEBF7',   // xanh dương pastel
        4 => 'FFF2CC',   // vàng kem nhạt
    ];

    public function __construct(
        private readonly ShipmentService $shipments,
    ) {}

    /**
     * @return string Đường dẫn file XLSX tạm (caller xử lý download + cleanup)
     */
    public function exportPeriod(string $period, User $user): string
    {
        $cols = config('shipment_columns', []);
        // Filter cột user không được xem
        $cols = array_filter($cols, fn ($c) => $user->canViewColumn($c['key']));
        $cols = array_values($cols);

        $rows = $this->shipments->listForGrid($period, $user);

        $spreadsheet = new Spreadsheet();
        $spreadsheet->removeSheetByIndex(0);   // bỏ sheet default

        $this->writeSheet($spreadsheet, 'HÀNG NHẬP', $cols, $rows['import'], 0);
        $this->writeSheet($spreadsheet, 'HÀNG XUẤT', $cols, $rows['export'], 1);

        $spreadsheet->setActiveSheetIndex(0);

        $tempFile = tempnam(sys_get_temp_dir(), 'shipments_' . $period . '_');
        $writer = new Xlsx($spreadsheet);
        $writer->save($tempFile);

        return $tempFile;
    }

    /**
     * @param iterable<int,array> $rows
     */
    private function writeSheet(Spreadsheet $book, string $name, array $cols, iterable $rows, int $index): void
    {
        $sheet = $book->createSheet($index);
        $sheet->setTitle($name);

        // Tab color
        $sheet->getTabColor()->setRGB($index === 0 ? '24D39F' : '0153A9');

        // Header row
        foreach ($cols as $ci => $col) {
            $cellCol = $this->colLetter($ci);
            $sheet->setCellValue($cellCol . '1', $col['title']);

            // Width
            if (! empty($col['width'])) {
                $sheet->getColumnDimension($cellCol)->setWidth(max(8, $col['width'] / 7));
            }
        }

        // Style header
        $lastCol = $this->colLetter(count($cols) - 1);
        $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['rgb' => '000000'], 'size' => 11],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
                'wrapText'   => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']],
            ],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(36);

        // Header background theo group
        foreach ($cols as $ci => $col) {
            $cellCol = $this->colLetter($ci);
            $color = self::GROUP_COLORS[$col['group'] ?? 1] ?? 'E1E6F1';
            $sheet->getStyle($cellCol . '1')->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setRGB($color);
        }

        // Data rows
        $rowIdx = 2;
        foreach ($rows as $row) {
            foreach ($cols as $ci => $col) {
                $value = $row[$col['key']] ?? null;
                $cellCol = $this->colLetter($ci);
                $cellRef = $cellCol . $rowIdx;

                // Format theo type
                if ($value !== null && $value !== '') {
                    if (($col['type'] ?? null) === 'date') {
                        // Excel native date — value số ngày từ 1900-01-01
                        try {
                            $dt = is_string($value) ? new \DateTime($value) : $value;
                            $excelDate = \PhpOffice\PhpSpreadsheet\Shared\Date::PHPToExcel($dt);
                            $sheet->setCellValue($cellRef, $excelDate);
                            $sheet->getStyle($cellRef)->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                        } catch (\Exception $e) {
                            $sheet->setCellValue($cellRef, $value);
                        }
                    } elseif (($col['type'] ?? null) === 'vnd' || ($col['type'] ?? null) === 'number') {
                        $sheet->setCellValue($cellRef, (float) $value);
                        $format = ($col['type'] === 'vnd')
                            ? '#,##0" VNĐ"'
                            : '#,##0';
                        $sheet->getStyle($cellRef)->getNumberFormat()->setFormatCode($format);
                        $sheet->getStyle($cellRef)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    } else {
                        $sheet->setCellValue($cellRef, (string) $value);
                    }
                }

                // Border
                $sheet->getStyle($cellRef)->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CCCCCC']],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
            }
            $rowIdx++;
        }

        // Freeze header + first column
        $sheet->freezePane('B2');

        // Auto filter
        $sheet->setAutoFilter("A1:{$lastCol}1");
    }

    /** Convert column index (0-based) to Excel letter (A, B, ..., Z, AA, AB...). */
    private function colLetter(int $index): string
    {
        $letter = '';
        $i = $index;
        do {
            $letter = chr(65 + ($i % 26)) . $letter;
            $i = intdiv($i, 26) - 1;
        } while ($i >= 0);
        return $letter;
    }
}
