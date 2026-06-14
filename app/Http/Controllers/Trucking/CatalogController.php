<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Cài đặt Trucking — danh mục (lazy theo tab), khách hàng, đội xe, cấu hình, phí tuyến/giá dầu. */
class CatalogController extends BaseTruckingController
{
    /** Trang Cài đặt — chỉ nạp ĐẾM cho sidebar; mỗi tab lazy-load khi click. */
    public function index()
    {
        return view('trucking2.cai-dat', $this->pageData(['counts' => $this->svc->catalogCounts()], 'settings.update', 'settings.update'));
    }

    /** Dữ liệu TƯƠI của 1 tab Cài đặt (lazy-load khi click tab). */
    public function data(string $type): JsonResponse
    {
        return response()->json(['ok' => true, 'cfg' => $this->svc->catalogData($type)]);
    }

    /** Lưu RIÊNG 1 danh mục lookup (mỗi tab Cài đặt = 1 bảng). */
    public function save(Request $request, string $type): JsonResponse
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

    /** Lưu cấu hình đơn (VAT mặc định, Free time, cảnh báo hạn…). */
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
}
