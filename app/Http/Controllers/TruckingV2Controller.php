<?php

namespace App\Http\Controllers;

use App\Models\TruckingLocation;
use App\Models\TruckingShipment;
use App\Models\TruckingStatement;
use App\Models\TruckingWarehouse;
use App\Services\TruckingV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Trucking v2 — giao diện record + popup (thay luồng Luckysheet).
 * Controller mỏng; serialize/persist nằm ở TruckingV2Service.
 */
class TruckingV2Controller extends Controller
{
    public function __construct(private readonly TruckingV2Service $svc) {}

    /** Trang Lô hàng — 2 sheet HPH/ICD + popup chi phí/doanh thu/thông tin. */
    public function shipments()
    {
        return view('trucking2.lo-hang', $this->pageData([
            'page' => $this->svc->pagedShipments('icd', []),  // trang 1 (server-side paginate)
            'cfg'  => $this->svc->shipmentBoardConfig(),       // tối thiểu; danh mục dropdown lazy-load khi mở popup
            'sibs' => $this->svc->siblingsList('icd'),        // picker "ra hộ" (rút gọn)
        ]));
    }

    /** Bảng giá của 1 khách (lazy-load cho trang Bảng giá). */
    public function customerPrices(Request $request): JsonResponse
    {
        $name = (string) $request->query('customer', '');
        return response()->json(['ok' => true, 'priceList' => $this->svc->customerPriceList($name)]);
    }

    /** Trang Bảng giá — bảng giá đã gửi theo từng khách. */
    public function prices()
    {
        // Bảng giá chỉ cần khách + địa điểm; bảng giá từng khách lazy-load, danh mục khác bỏ.
        return view('trucking2.bang-gia', $this->pageData(['cfg' => $this->svc->priceBookConfig()], 'prices.update', 'prices.update'));
    }

    /** Trang Bảng kê — gom lô theo khách + kỳ, theo dõi công nợ. */
    public function statements()
    {
        // KePage chỉ cần danh sách bảng kê (tóm tắt) → không nạp shipments/cfg/lines.
        return view('trucking2.bang-ke', $this->pageData([
            'ke' => $this->svc->statementsForList(),
        ], 'statements.update', 'statements.delete'));
    }

    /** Trang Tạo bảng kê mới (tách riêng khỏi danh sách để dễ maintain). */
    public function createStatement()
    {
        return view('trucking2.bang-ke-tao', $this->pageData([
            'hph' => $this->svc->shipments('hph'),
            'icd' => $this->svc->shipments('icd'),
            'cfg' => $this->svc->config(),
        ], 'statements.create', 'statements.delete'));
    }

    /** Trang Xem bảng kê đã lưu — chỉ nạp bảng kê (nhẹ). Đối soát/bảng giá tải lazy. */
    public function viewStatement(TruckingStatement $statement)
    {
        return view('trucking2.bang-ke-xem', $this->pageData([
            'st' => $this->svc->statementToArray($statement),
        ], 'statements.update', 'statements.delete'));
    }

    /**
     * Ngữ cảnh định giá cho 1 bảng kê (tải lazy khi cần đối soát/tính lại):
     * chỉ lô có trong bảng kê + bảng giá của đúng khách → tránh nạp toàn bộ lô + mọi khách.
     */
    public function statementContext(TruckingStatement $statement): JsonResponse
    {
        $st  = $this->svc->statementToArray($statement);
        $ids = array_filter(array_map(fn ($l) => $l['id'] ?? null, $st['lines'] ?? []));

        return response()->json([
            'ok'    => true,
            'cfg'   => $this->svc->pricingCfg($st['customer'] ?? null),
            'ships' => $this->svc->shipmentsByIds($ids),
        ]);
    }

    /**
     * Xuất bảng kê ra Excel theo MẪU CHÍNH THỨC (STATEMENT ACCOUNT — XUẤT-HPH):
     * nạp file template (giữ nguyên định dạng), điền dữ liệu + giãn/thu số dòng,
     * công thức Tổng tự cập nhật theo range thực.
     */
    public function exportStatement(TruckingStatement $statement): StreamedResponse
    {
        $st   = $this->svc->statementToArray($statement);
        $info = $st['info'] ?? [];

        $tplPath = storage_path('app/trucking/statement-template.xlsx');
        abort_unless(is_file($tplPath), 404, 'Thiếu file mẫu bảng kê.');
        $ss = IOFactory::load($tplPath);
        $sh = $ss->getActiveSheet();
        $sh->setAutoFilter('');   // phòng vệ: không để AutoFilter range thành #REF! sau khi giãn/thu dòng

        $dmy = fn (?string $iso) => $iso && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $iso, $m) ? "{$m[3]}/{$m[2]}/{$m[1]}" : (string) $iso;

        // Bên mua + Debit/Date
        $sh->setCellValue('A8', 'BÊN MUA: ' . ($st['customer'] ?? ''));
        $sh->setCellValue('A9', 'Địa chỉ: ' . ($info['address'] ?? ''));
        $sh->setCellValue('A10', 'MST: ' . ($info['taxCode'] ?? ''));
        $sh->setCellValueExplicit('O18', (string) ($st['no'] ?? ''), DataType::TYPE_STRING);
        $sh->setCellValueExplicit('O19', $dmy($st['date'] ?? null), DataType::TYPE_STRING);

        $lines    = array_values($st['lines'] ?? []);
        $M        = max(count($lines), 1);     // luôn giữ ≥1 dòng vùng dữ liệu
        $start    = 22;
        $tplRows  = 46;                         // mẫu có 46 dòng (22..67)
        $delta    = $M - $tplRows;

        // Backfill từ lô hàng còn sống (cho bảng kê cũ chưa có snapshot đầy đủ)
        $ids   = array_values(array_filter(array_map(fn ($l) => $l['id'] ?? null, $lines), 'is_numeric'));
        $ships = $ids
            ? TruckingShipment::with('costLines')->whereIn('id', $ids)->get()->keyBy('id')
            : collect();
        $val = fn ($a, $b) => trim((string) ($a ?? '')) !== '' ? $a : $b;   // ưu tiên snapshot, rỗng thì lấy lô

        // Map mã/tên → TÊN địa điểm & kho (tuyến hiển thị tên tường minh, không dùng ký hiệu)
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
        $whN  = fn ($v) => ($v = trim((string) $v)) !== '' ? ($whMap[$v] ?? $v) : '';

        if ($delta < 0) {
            $sh->removeRow($start + 1, -$delta);                // bỏ bớt từ dòng 23
        } elseif ($delta > 0) {
            $sh->insertNewRowBefore($start + 1, $delta);        // chèn thêm sau dòng 22
            $h = $sh->getRowDimension($start)->getRowHeight();
            for ($r = $start + 1; $r <= $start + $delta; $r++) {
                $sh->duplicateStyle($sh->getStyle("A{$start}:P{$start}"), "A{$r}:P{$r}");
                $sh->getRowDimension($r)->setRowHeight($h);
            }
        }

        $sumCuoc = 0; $sumThanhLy = 0; $sumTong = 0;
        foreach ($lines as $i => $l) {
            $r = $start + $i;
            $s = $ships[$l['id'] ?? null] ?? null;

            $declNo   = $val($l['declNo']   ?? '', $s?->declaration_no);
            $contType = $val($l['contType'] ?? '', $s?->cont_type);
            $inv      = $val($l['inv']      ?? '', $s?->inv);
            $contNo   = $val($l['contNo']   ?? '', $s?->cont_no);
            $bks      = $val($l['bks']      ?? '', $s?->bks_vao ?: $s?->bks_ra);
            $note     = $val($l['note']     ?? '', $s?->ghi_chu);

            // Tuyến vận chuyển — ưu tiên tuyến ĐÃ HIỂN THỊ (detail.route: "Nơi lấy → Nơi hạ"),
            // đổi mỗi điểm sang TÊN tường minh (địa điểm → kho → giữ nguyên nếu không khớp).
            // Bảng kê cũ chưa có snapshot route → dựng từ Nơi lấy + Kho + Nơi hạ của lô.
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
                $fromLoc = $s ? $s->from_loc : ($l['from'] ?? '');
                $toLoc   = $s ? $s->to_loc   : ($l['to'] ?? '');
                $khoStr  = $s ? (string) $s->kho : '';
                $parts   = [];
                if (trim((string) $fromLoc) !== '') $parts[] = $locN($fromLoc);
                foreach (preg_split('/\s*,\s*/', $khoStr, -1, PREG_SPLIT_NO_EMPTY) as $k) $parts[] = $whN($k);
                if (trim((string) $toLoc) !== '') $parts[] = $locN($toLoc);
                $route = implode(' → ', array_filter($parts, fn ($p) => trim((string) $p) !== ''));
            }

            // Phí thanh lý: snapshot, rỗng thì lấy dòng chi phí src=thanhLyFee của lô
            $thanhLy = (int) ($l['thanhLy'] ?? 0);
            if ($thanhLy === 0 && $s) {
                $thanhLy = (int) round((float) $s->costLines->where('src', 'thanhLyFee')->sum('amount'));
            }
            // Cước (chưa VAT): snapshot cuoc, rỗng thì lấy phải thu đã lưu
            $cuoc = (int) (($l['cuoc'] ?? 0) ?: ($l['phaiThu'] ?? 0));
            $tong = $cuoc + $thanhLy;
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
            $sh->setCellValue("N{$r}", $thanhLy);
            $sh->setCellValue("O{$r}", $tong);          // số cụ thể, không dùng SUM
            $sh->setCellValue("P{$r}", $note);
        }

        // Dòng Tổng — điền SỐ CỤ THỂ (không dùng công thức SUM)
        $totalRow = $start + $M;
        $sh->setCellValue("J{$totalRow}", $sumCuoc);
        $sh->setCellValue("K{$totalRow}", 0);
        $sh->setCellValue("L{$totalRow}", 0);
        $sh->setCellValue("M{$totalRow}", 0);
        $sh->setCellValue("N{$totalRow}", $sumThanhLy);
        $sh->setCellValue("O{$totalRow}", $sumTong);

        // Ngày tháng (J70 đã merge J:P) dời theo delta
        $d = explode('-', (string) ($st['date'] ?? ''));
        if (count($d) === 3) {
            $sh->setCellValue('J' . (70 + $delta), "Ngày {$d[2]} tháng {$d[1]} năm {$d[0]}");
        }

        $writer   = new XlsxWriter($ss);
        $filename = 'bang-ke-' . preg_replace('/[^\w\-]+/u', '-', (string) ($st['no'] ?? 'export')) . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache',
        ]);
    }

    /** Trang Cài đặt — chỉ nạp ĐẾM cho sidebar; mỗi tab lazy-load khi click (catalogData). */
    public function settings()
    {
        return view('trucking2.cai-dat', $this->pageData(['counts' => $this->svc->catalogCounts()], 'settings.update', 'settings.update'));
    }

    /** Dữ liệu TƯƠI của 1 tab Cài đặt (lazy-load khi click tab). */
    public function catalogData(string $type): JsonResponse
    {
        return response()->json(['ok' => true, 'cfg' => $this->svc->catalogData($type)]);
    }

    /**
     * Dữ liệu chung cho mọi trang: quyền (canEdit/canDelete theo ĐÚNG tính năng của trang)
     * + boot (inline, không cần fetch). Mỗi trang truyền quyền sửa/xóa tương ứng.
     */
    private function pageData(array $boot, string $editPerm = 'shipments.update', string $deletePerm = 'shipments.delete'): array
    {
        $u = $this->user();
        return [
            'canEdit'   => $u->can($editPerm),
            'canDelete' => $u->can($deletePerm),
            'boot'      => $boot,
        ];
    }

    /** Toàn bộ dữ liệu cho 1 lần khởi tạo app. */
    public function bootstrap(): JsonResponse
    {
        return response()->json($this->svc->bootstrap());
    }

    // ===================== Shipments =====================
    /** Master data đầy đủ cho dropdown trong popup (lazy-load lần đầu mở popup ở trang Lô hàng). */
    public function configData(): JsonResponse
    {
        return response()->json(['ok' => true, 'cfg' => $this->svc->config(withPrices: false)]);
    }

    /** Trang Lô hàng — 1 trang (20 lô) + aggregate toàn cục. JSON cho client fetch khi đổi trang/tìm/lọc/sort. */
    public function shipmentsPage(Request $request): JsonResponse
    {
        $params = $request->only(['page', 'q', 'filter', 'follow', 'sort', 'dir', 'all']);
        return response()->json(['ok' => true] + $this->svc->pagedShipments('icd', $params));
    }

    public function storeShipment(Request $request): JsonResponse
    {
        $data = $this->validateShipment($request);
        $ship = $this->svc->saveShipment($data, $data['sheet']);

        return response()->json(['ok' => true, 'ship' => $this->svc->shipmentToArray($ship)]);
    }

    public function updateShipment(Request $request, TruckingShipment $shipment): JsonResponse
    {
        $data = $this->validateShipment($request);
        // Lưu TỪNG PHẦN: chỉ field client gửi trong "fields" mới ghi đè (tránh đè thay đổi người khác).
        $only = $request->input('fields');
        $only = is_array($only) && $only ? array_values(array_filter(array_map('strval', $only))) : null;
        $ship = $this->svc->saveShipment($data, $data['sheet'], $shipment, $only);

        return response()->json(['ok' => true, 'ship' => $this->svc->shipmentToArray($ship)]);
    }

    public function destroyShipment(TruckingShipment $shipment): JsonResponse
    {
        $shipment->delete();
        return response()->json(['ok' => true]);
    }

    /** Kiểm tra trước (dry-run) — không ghi DB, trả danh sách lỗi từng dòng. */
    public function checkShipments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'rows' => ['present', 'array'],
        ]);
        return response()->json(['ok' => true] + $this->svc->validateShipments($data['rows']));
    }

    /** Import lô hàng từ Excel — ALL-OR-NOTHING (1 lỗi là không import gì). */
    public function importShipments(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sheet' => ['required', 'in:hph,icd'],
            'rows'  => ['present', 'array'],
        ]);
        $res = $this->svc->importShipments($data['sheet'], $data['rows']);

        return response()->json(['ok' => true] + $res);
    }

    // ===================== Config =====================
    /** Lưu RIÊNG 1 danh mục lookup (mỗi tab Cài đặt = 1 bảng). */
    public function saveCatalog(Request $request, string $type): JsonResponse
    {
        abort_unless(in_array($type, $this->svc->catalogKeys(), true), 404);
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveCatalog($type, $cfg);

        return response()->json(['ok' => true]);
    }

    /** Lưu danh mục Khách hàng (+ thông tin; bảng giá chỉ đụng khi gửi priceList). */
    public function saveCustomers(Request $request): JsonResponse
    {
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveCustomers($cfg);

        return response()->json(['ok' => true]);
    }

    /** Đổi tên khách hàng (giữ liên kết). */
    public function renameCustomer(Request $request): JsonResponse
    {
        $data = $request->validate(['old' => ['required', 'string'], 'new' => ['required', 'string']]);
        return response()->json($this->svc->renameCustomer($data['old'], $data['new']));
    }

    /** Lưu danh mục Đội xe (biển số + loại). */
    public function saveVehicles(Request $request): JsonResponse
    {
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveVehicles($cfg);

        return response()->json(['ok' => true]);
    }

    /** Lưu cấu hình đơn (VAT mặc định, Free time). */
    public function saveSettings(Request $request): JsonResponse
    {
        $cfg = $request->validate(['cfg' => ['required', 'array']])['cfg'];
        $this->svc->saveSettings($cfg);

        return response()->json(['ok' => true]);
    }

    /** Import bảng giá 1 khách từ Excel (dòng đã parse phía client). */
    public function importPrices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer' => ['required', 'string'],
            'rows'     => ['present', 'array'],
            'replace'  => ['nullable', 'boolean'],
        ]);
        $res = $this->svc->importPriceRows($data['customer'], $data['rows'], (bool) ($data['replace'] ?? false));

        return response()->json(['ok' => true] + $res);
    }

    // ===================== Statements =====================
    public function storeStatement(Request $request): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'array']])['statement'];
        $st = $this->svc->saveStatement($data);

        return response()->json(['ok' => true, 'statement' => $this->svc->statementToArray($st)]);
    }

    public function updateStatement(Request $request, TruckingStatement $statement): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'array']])['statement'];
        $st = $this->svc->saveStatement($data, $statement);

        return response()->json(['ok' => true, 'statement' => $this->svc->statementToArray($st)]);
    }

    public function destroyStatement(TruckingStatement $statement): JsonResponse
    {
        $statement->delete();
        return response()->json(['ok' => true]);
    }

    // ===================== helpers =====================
    private function validateShipment(Request $request): array
    {
        $data = $request->validate([
            'sheet' => ['required', 'in:hph,icd'],
            'ship'  => ['required', 'array'],
        ]);

        return $data['ship'] + ['sheet' => $data['sheet']];
    }

    private function user()
    {
        return request()->user();
    }
}
