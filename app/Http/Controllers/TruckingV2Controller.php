<?php

namespace App\Http\Controllers;

use App\Models\TruckingShipment;
use App\Models\TruckingStatement;
use App\Services\TruckingV2Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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
            'hph' => $this->svc->shipments('hph'),
            'icd' => $this->svc->shipments('icd'),
            'cfg' => $this->svc->config(),
        ]));
    }

    /** Trang Bảng giá — bảng giá đã gửi theo từng khách. */
    public function prices()
    {
        return view('trucking2.bang-gia', $this->pageData(['cfg' => $this->svc->config()]));
    }

    /** Trang Bảng kê — gom lô theo khách + kỳ, theo dõi công nợ. */
    public function statements()
    {
        return view('trucking2.bang-ke', $this->pageData([
            'hph' => $this->svc->shipments('hph'),
            'icd' => $this->svc->shipments('icd'),
            'cfg' => $this->svc->config(),
            'ke'  => $this->svc->statements(),
        ]));
    }

    /** Trang Tạo bảng kê mới (tách riêng khỏi danh sách để dễ maintain). */
    public function createStatement()
    {
        return view('trucking2.bang-ke-tao', $this->pageData([
            'hph' => $this->svc->shipments('hph'),
            'icd' => $this->svc->shipments('icd'),
            'cfg' => $this->svc->config(),
        ]));
    }

    /** Trang Cài đặt — danh mục master data (sidebar). */
    public function settings()
    {
        return view('trucking2.cai-dat', $this->pageData(['cfg' => $this->svc->config()]));
    }

    /** Dữ liệu chung cho mọi trang: quyền + boot (inline, không cần fetch). */
    private function pageData(array $boot): array
    {
        return [
            'canEdit'   => $this->user()->can('shipments.update'),
            'canDelete' => $this->user()->can('shipments.delete'),
            'boot'      => $boot,
        ];
    }

    /** Toàn bộ dữ liệu cho 1 lần khởi tạo app. */
    public function bootstrap(): JsonResponse
    {
        return response()->json($this->svc->bootstrap());
    }

    // ===================== Shipments =====================
    public function storeShipment(Request $request): JsonResponse
    {
        $data = $this->validateShipment($request);
        $ship = $this->svc->saveShipment($data, $data['sheet']);

        return response()->json(['ok' => true, 'ship' => $this->svc->shipmentToArray($ship)]);
    }

    public function updateShipment(Request $request, TruckingShipment $shipment): JsonResponse
    {
        $data = $this->validateShipment($request);
        $ship = $this->svc->saveShipment($data, $data['sheet'], $shipment);

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
