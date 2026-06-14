<?php

namespace App\Http\Controllers;

use App\Models\TruckingDriver;
use App\Models\TruckingLocation;
use App\Models\TruckingShipment;
use App\Models\TruckingStatement;
use App\Models\TruckingTripCostBatch;
use App\Models\TruckingVehicle;
use App\Models\TruckingVehicleCost;
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

        // XUẤT THEO SNAPSHOT ĐÃ LƯU — KHÔNG đọc lô realtime: bảng kê là bản đã chốt,
        // không được đổi theo dữ liệu lô sửa/xóa sau khi lập.

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

            // Tất cả lấy từ SNAPSHOT của dòng bảng kê (không đọc lô realtime)
            $declNo   = $l['declNo']   ?? '';
            $contType = $l['contType'] ?? '';
            $inv      = $l['inv']      ?? '';
            $contNo   = $l['contNo']   ?? '';
            $bks      = $l['bks']      ?? '';
            $note     = $l['note']     ?? '';

            // Tuyến vận chuyển — lấy từ snapshot (detail.route đã chốt khi lưu),
            // đổi mỗi điểm sang TÊN tường minh; bảng kê cũ thiếu route → dựng từ Nơi lấy/Nơi hạ đã lưu.
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

            // Phí thanh lý & cước (chưa VAT) — lấy thẳng từ snapshot đã lưu
            $thanhLy = (int) ($l['thanhLy'] ?? 0);
            $cuoc    = (int) (($l['cuoc'] ?? 0) ?: ($l['phaiThu'] ?? 0));
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

    /** Lưu cấu hình Phí tuyến đường (repeater). */
    public function saveRouteFees(Request $request): JsonResponse
    {
        $rows = $request->input('cfg.routeFees', $request->input('rows', []));
        $this->svc->saveRouteFees(is_array($rows) ? $rows : []);

        return response()->json(['ok' => true]);
    }

    /** Lưu Bảng giá dầu (repeater theo khoảng ngày). */
    public function saveFuelPrices(Request $request): JsonResponse
    {
        $rows = $request->input('cfg.fuelPrices', $request->input('rows', []));
        $this->svc->saveFuelPrices(is_array($rows) ? $rows : []);

        return response()->json(['ok' => true]);
    }

    // ===================== Hồ sơ lái xe =====================
    /** Lưu hồ sơ lái xe (tên/SĐT/ngày/tài khoản) — tài liệu quản lý qua upload riêng. */
    public function saveDrivers(Request $request): JsonResponse
    {
        $rows = $request->input('cfg.drivers', $request->input('rows', []));
        $this->svc->saveDrivers(is_array($rows) ? $rows : []);

        return response()->json(['ok' => true, 'drivers' => $this->svc->catalogData('drivers')['drivers']]);
    }

    /** Tải tài liệu (CCCD/bằng lái — file hoặc ảnh, nhiều file 1 lần). */
    public function uploadDriverDocs(Request $request, TruckingDriver $driver): JsonResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,pdf,doc,docx,xls,xlsx,csv'],
            'type'    => ['nullable', 'string', 'max:60'],
        ]);
        $docs = $this->svc->uploadDriverDocs($driver, $request->file('files', []), (string) $request->input('type', ''));

        return response()->json(['ok' => true, 'docs' => $docs]);
    }

    public function deleteDriverDoc(TruckingDriver $driver, int $idx): JsonResponse
    {
        $docs = $this->svc->deleteDriverDoc($driver, $idx);   // $idx = id attachment
        return response()->json(['ok' => true, 'docs' => $docs]);
    }

    /** Stream 1 file (bảng attachments tập trung) — disk-agnostic (local/S3), kiểm soát quyền theo owner. */
    public function showAttachment(\App\Models\TruckingAttachment $attachment)
    {
        $u = auth()->user();
        if ($attachment->group === 'costPhoto') {
            $allowed = $u?->can('settings.view')
                || ($u?->can('spend.request') && TruckingVehicleCost::where('created_by', $u->id)->whereJsonContains('photos', $attachment->id)->exists());
        } else {
            $allowed = $u?->can('settings.view');
        }
        abort_unless($allowed, 403);
        $disk = \Illuminate\Support\Facades\Storage::disk($attachment->disk);
        abort_unless($disk->exists($attachment->path), 404);
        return $disk->response($attachment->path, $attachment->name ?: 'file', ['Content-Type' => $attachment->mime ?: 'application/octet-stream']);
    }

    // ===================== Phí xe nội bộ (trip cost) — mô hình KỲ (snapshot) =====================
    /** Danh sách kỳ phí xe (trang chủ Phí xe nội bộ). */
    public function tripCostPage()
    {
        return view('trucking2.phi-xe', $this->pageData([
            'batches' => $this->svc->tripBatchesForList(),
        ]));
    }

    /** Trang Tạo kỳ phí xe mới. */
    public function createTripCost()
    {
        return view('trucking2.phi-xe-tao', $this->pageData([], 'shipments.create'));
    }

    /** Tính (AJAX): gom lô có giờ xe ra trong kỳ + gợi ý phí. */
    public function tripCostCompute(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to   = $request->query('to') ?: null;
        return response()->json(['ok' => true] + $this->svc->computeTripCosts($from, $to));
    }

    /** Trang Xem/Sửa 1 kỳ đã lưu (snapshot, nhẹ). */
    public function viewTripCost(TruckingTripCostBatch $tripCost)
    {
        return view('trucking2.phi-xe-xem', $this->pageData([
            'batch' => $this->svc->tripBatchToArray($tripCost),
        ]));
    }

    /** Ngữ cảnh "Tính lại" cho kỳ đã lưu (tải lazy khi bấm). */
    public function tripCostContext(TruckingTripCostBatch $tripCost): JsonResponse
    {
        return response()->json(['ok' => true] + $this->svc->tripBatchContext($tripCost));
    }

    public function storeTripCost(Request $request): JsonResponse
    {
        $data = $request->validate(['batch' => ['required', 'array']])['batch'];
        $b = $this->svc->saveTripBatch($data);

        return response()->json(['ok' => true, 'batch' => $this->svc->tripBatchToArray($b)]);
    }

    public function updateTripCost(Request $request, TruckingTripCostBatch $tripCost): JsonResponse
    {
        $data = $request->validate(['batch' => ['required', 'array']])['batch'];
        $b = $this->svc->saveTripBatch($data, $tripCost);

        return response()->json(['ok' => true, 'batch' => $this->svc->tripBatchToArray($b)]);
    }

    public function destroyTripCost(TruckingTripCostBatch $tripCost): JsonResponse
    {
        $tripCost->delete();
        return response()->json(['ok' => true]);
    }

    // ===================== Quản lý xe (MBF) =====================
    /** Trang Quản lý xe — danh sách xe MBF nội bộ. */
    public function fleet()
    {
        return view('trucking2.quan-ly-xe', $this->pageData([
            'vehicles'      => $this->svc->mbfVehicles(),
            'expiringCosts' => $this->svc->expiringVehicleCosts(),
            'pendingCosts'  => $this->svc->pendingVehicleCosts(),
            'costItems'     => $this->svc->costItemNames(),
        ], 'settings.update', 'settings.update'));
    }

    /** Trang gửi yêu cầu chi (mobile SPA) — cần đăng nhập + quyền spend.request. */
    public function spendRequestPage()
    {
        $u = auth()->user();
        $can = $u && $u->can('spend.request');
        $boot = ['auth' => ['logged' => (bool) $u, 'name' => $u?->name ?? '', 'canRequest' => (bool) $can]];
        if ($can) {
            $boot = array_merge($boot, $this->svc->publicRequestData());
            $boot['history'] = $this->svc->spendRequestHistory($u->id);
        }
        return view('trucking2.yeu-cau-chi', ['boot' => $boot]);
    }

    /** Đăng nhập mobile (đơn giản, BỎ 2FA) — chỉ cho luồng yêu cầu chi. */
    public function spendLogin(Request $request): JsonResponse
    {
        // Validate THỦ CÔNG (trả 200 + ok:false) để lỗi hiện NGAY trong form, tiếng Việt — không bật hộp lỗi.
        $email = trim((string) $request->input('email'));
        $password = (string) $request->input('password');
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['ok' => false, 'message' => 'Email không hợp lệ. Vui lòng nhập đúng địa chỉ email.']);
        }
        if ($password === '') {
            return response()->json(['ok' => false, 'message' => 'Vui lòng nhập mật khẩu.']);
        }
        $remember = ! $request->has('remember') || $request->boolean('remember');   // mặc định LUÔN đăng nhập
        if (! \Illuminate\Support\Facades\Auth::attempt(['email' => $email, 'password' => $password], $remember)) {
            return response()->json(['ok' => false, 'message' => 'Email hoặc mật khẩu không đúng.']);
        }
        $u = auth()->user();
        if (! $u->can('spend.request')) {
            \Illuminate\Support\Facades\Auth::logout();
            return response()->json(['ok' => false, 'message' => 'Tài khoản chưa được cấp quyền gửi yêu cầu chi. Liên hệ quản trị.']);
        }
        $request->session()->regenerate();
        return response()->json(['ok' => true, 'name' => $u->name]);
    }

    public function spendLogout(Request $request): JsonResponse
    {
        \Illuminate\Support\Facades\Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return response()->json(['ok' => true]);
    }

    /** Nhận yêu cầu chi (check định mức km) — kèm ảnh thực tế. Cần đăng nhập + quyền. */
    public function submitSpendRequest(Request $request): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) {
            return response()->json(['ok' => false, 'message' => 'Phiên đăng nhập hết hạn — vui lòng đăng nhập lại.']);
        }
        $data = $request->validate([
            'vehicleId' => ['required'],
            'costItem'  => ['required', 'string', 'max:120'],
            'date'      => ['nullable', 'string', 'max:20'],
            'amount'    => ['required'],
            'km'        => ['nullable', 'string', 'max:20'],
            'photos'    => ['nullable', 'array', 'max:12'],
            'photos.*'  => ['file', 'image', 'max:20480'],
        ], $this->spendValidationMessages());
        return response()->json($this->svc->createSpendRequest($data, $request->file('photos', [])));
    }

    /** Lịch sử yêu cầu chi của chính user (mobile). */
    public function spendHistory(): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) return response()->json(['ok' => false], 403);
        return response()->json(['ok' => true, 'history' => $this->svc->spendRequestHistory(auth()->id())]);
    }

    /** Tài xế tự hủy phiếu của mình (khi chưa duyệt). */
    public function cancelMySpendRequest(int $cost): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) return response()->json(['ok' => false, 'message' => 'Không có quyền.']);
        return response()->json($this->svc->cancelSpendRequestByOwner(auth()->id(), $cost));
    }

    /** Tài xế SỬA phiếu của mình (khi chưa duyệt) — kèm ảnh (keep + mới). */
    public function updateMySpendRequest(Request $request, int $cost): JsonResponse
    {
        if (! auth()->user()?->can('spend.request')) return response()->json(['ok' => false, 'message' => 'Phiên đăng nhập hết hạn — đăng nhập lại.']);
        $data = $request->validate([
            'costItem'  => ['required', 'string', 'max:120'],
            'date'      => ['nullable', 'string', 'max:20'],
            'amount'    => ['required'],
            'km'        => ['nullable', 'string', 'max:20'],
            'keep'      => ['nullable', 'array', 'max:12'],
            'keep.*'    => ['string', 'max:120'],
            'photos'    => ['nullable', 'array', 'max:12'],
            'photos.*'  => ['file', 'image', 'max:20480'],
        ], $this->spendValidationMessages());
        return response()->json($this->svc->updateSpendRequestByOwner(auth()->id(), $cost, $data, $request->file('photos', [])));
    }

    /** Thông báo validate tiếng Việt cho gửi/sửa yêu cầu chi. */
    private function spendValidationMessages(): array
    {
        return [
            'required'      => 'Vui lòng nhập đầy đủ thông tin bắt buộc.',
            'photos.max'    => 'Tối đa 12 ảnh.',
            'photos.*.image' => 'Tệp đính kèm phải là ảnh.',
            'photos.*.max'  => 'Mỗi ảnh tối đa 20MB.',
            'photos.*.file' => 'Tệp không hợp lệ.',
            'max'           => 'Dữ liệu nhập quá dài.',
        ];
    }

    /** Admin hủy phiếu chi (khi chưa thanh toán) — từ Quản lý xe. */
    public function adminCancelCost(TruckingVehicleCost $cost): JsonResponse
    {
        return response()->json($this->svc->cancelVehicleCost($cost, auth()->id()));
    }

    /** Internal: upload ảnh thực tế cho phiếu chi (CostModal) → trả danh sách (kèm id + url) để đính vào phiếu. */
    public function uploadCostPhotos(Request $request, TruckingVehicle $vehicle): JsonResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'max:12'],
            'files.*' => ['file', 'image', 'max:20480'],
        ]);
        return response()->json(['ok' => true, 'photos' => $this->svc->storeCostPhotos($vehicle, $request->file('files', []))]);
    }

    /** Tạo nhanh khoản chi phí (Combo tên phiếu chi) → trả danh mục mới. */
    public function addVehicleCostItem(Request $request): JsonResponse
    {
        $name = (string) $request->validate(['name' => ['required', 'string', 'max:120']])['name'];
        return response()->json(['ok' => true, 'costItems' => $this->svc->addCostItem($name)]);
    }

    /** Mở xe — chỉ nạp THÔNG TIN nền (3 nhóm con lazy-load riêng theo tab). */
    public function vehicleData(TruckingVehicle $vehicle): JsonResponse
    {
        return response()->json(['ok' => true, 'vehicle' => $this->svc->vehicleBase($vehicle)]);
    }

    /** Lazy-load 1 nhóm con khi bấm tab: usages | costs | depreciations. */
    public function vehicleSection(TruckingVehicle $vehicle, string $section): JsonResponse
    {
        abort_unless(in_array($section, ['usages', 'costs', 'depreciations'], true), 404);
        return response()->json(['ok' => true] + $this->svc->vehicleSection($vehicle, $section));
    }

    /** Lưu — chỉ các phần gửi lên; trả về base + các phần vừa lưu (id mới). */
    public function saveVehicle(Request $request, TruckingVehicle $vehicle): JsonResponse
    {
        $data = $request->validate(['data' => ['required', 'array']])['data'];

        return response()->json(['ok' => true, 'vehicle' => $this->svc->saveVehicleManagement($vehicle, $data)]);
    }

    /** Tải tài liệu xe (nhiều file: ảnh/PDF/Word/Excel) → trả danh sách tài liệu mới. */
    public function uploadVehicleDocs(Request $request, TruckingVehicle $vehicle): JsonResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,pdf,doc,docx,xls,xlsx,csv'],
            'type'    => ['nullable', 'string', 'max:60'],
        ]);
        $docs = $this->svc->uploadVehicleDocs($vehicle, $request->file('files', []), (string) $request->input('type', ''));

        return response()->json(['ok' => true, 'docs' => $docs]);
    }

    public function deleteVehicleDoc(TruckingVehicle $vehicle, int $idx): JsonResponse
    {
        return response()->json(['ok' => true, 'docs' => $this->svc->deleteVehicleDoc($vehicle, $idx)]);   // $idx = id attachment
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
