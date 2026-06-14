<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingSetting;
use App\Models\TruckingVehicle;
use App\Models\TruckingVehicleCost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Quản lý xe (MBF) + Tài sản — danh sách, chi tiết (lazy theo tab), chi phí, tài liệu, tài sản. */
class FleetController extends BaseTruckingController
{
    /** Trang Quản lý xe — danh sách xe MBF nội bộ (tài sản lazy-load riêng). */
    public function index()
    {
        return view('trucking2.quan-ly-xe', $this->pageData([
            'vehicles'        => $this->svc->mbfVehicles(),
            'expiringCosts'   => $this->svc->expiringVehicleCosts(),
            'pendingCosts'    => $this->svc->pendingVehicleCosts(),
            'costItems'       => $this->svc->costItemNames(),
            'dueWarnDays'     => (int) TruckingSetting::get('due_warn_days', '30'),
        ], 'settings.update', 'settings.update'));
    }

    /** Danh sách tài sản — lazy-load khi mở tab Tài sản. */
    public function assetList(): JsonResponse
    {
        return response()->json(['ok' => true, 'assets' => $this->svc->assetList(), 'assetCategories' => $this->svc->assetCategories()]);
    }

    /** Tạo tài sản mới. */
    public function createAsset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'code'     => ['nullable', 'string', 'max:64', 'unique:trucking_vehicles,plate'],
            'category' => ['nullable', 'string', 'max:120'],
        ], [
            'name.required' => 'Vui lòng nhập tên tài sản.',
            'code.unique'   => 'Mã tài sản đã tồn tại, chọn mã khác.',
        ]);
        return response()->json(['ok' => true, 'asset' => $this->svc->createAsset($data)]);
    }

    /** Thêm nhanh loại tài sản → trả danh mục mới. */
    public function addAssetCategory(Request $request): JsonResponse
    {
        $name = (string) $request->validate(['name' => ['required', 'string', 'max:120']])['name'];
        return response()->json(['ok' => true, 'categories' => $this->svc->addAssetCategory($name)]);
    }

    /** Xóa tài sản (chỉ kind='asset'). */
    public function destroyAsset(TruckingVehicle $vehicle): JsonResponse
    {
        return response()->json(['ok' => $this->svc->destroyAsset($vehicle)]);
    }

    /** Admin hủy phiếu chi (khi chưa thanh toán). */
    public function adminCancelCost(TruckingVehicleCost $cost): JsonResponse
    {
        return response()->json($this->svc->cancelVehicleCost($cost, auth()->id()));
    }

    /** Upload ảnh thực tế cho phiếu chi (CostModal) → trả danh sách (kèm id + url). */
    public function uploadCostPhotos(Request $request, TruckingVehicle $vehicle): JsonResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'max:12'],
            'files.*' => ['file', 'image', 'max:20480'],
        ]);
        return response()->json(['ok' => true, 'photos' => $this->svc->storeCostPhotos($vehicle, $request->file('files', []))]);
    }

    /** Tạo nhanh khoản chi phí (Combo tên phiếu chi) → trả danh mục mới. */
    public function addCostItem(Request $request): JsonResponse
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

    /** Tải tài liệu xe (nhiều file: ảnh/PDF/Word/Excel). */
    public function uploadDocs(Request $request, TruckingVehicle $vehicle): JsonResponse
    {
        $request->validate([
            'files'   => ['required', 'array', 'max:20'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,gif,webp,heic,pdf,doc,docx,xls,xlsx,csv'],
            'type'    => ['nullable', 'string', 'max:60'],
        ]);
        $docs = $this->svc->uploadVehicleDocs($vehicle, $request->file('files', []), (string) $request->input('type', ''));

        return response()->json(['ok' => true, 'docs' => $docs]);
    }

    public function deleteDoc(TruckingVehicle $vehicle, int $idx): JsonResponse
    {
        return response()->json(['ok' => true, 'docs' => $this->svc->deleteVehicleDoc($vehicle, $idx)]);   // $idx = id attachment
    }
}
