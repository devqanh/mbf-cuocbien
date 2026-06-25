<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingFuelRefill;
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
            'vehicleCostTypes' => $this->svc->vehicleCostTypesOut(),   // định mức km dùng đúng danh mục Loại chi phí xe
            'payMethods'      => $this->svc->payMethodsOut(),          // hình thức thanh toán (cấu hình ở Cài đặt)
            'dueWarnDays'     => (int) TruckingSetting::get('due_warn_days', '30'),
        ], 'fleet.manage', 'fleet.manage'));
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

    /** Trang Quản lý chi phí — tổng hợp MỌI phiếu chi (xe + tài sản) để duyệt/thanh toán tập trung. */
    public function costManagement()
    {
        return view('trucking2.quan-ly-chi-phi', $this->pageData([
            'costTypes'      => $this->svc->vehicleCostTypesOut(),   // mặc định (xe)
            'assetCostTypes' => $this->svc->assetCostTypesOut(),     // dùng khi phiếu là tài sản
            'payMethods'     => $this->svc->payMethodsOut(),         // hình thức thanh toán (cấu hình ở Cài đặt)
            'suppliers' => $this->svc->supplierSuggestions(),
            'initial'   => $this->svc->costManagementData(['status' => 'action', 'page' => 1]),
        ], 'fleet.manage', 'fleet.manage'));
    }

    /** JSON: danh sách phiếu chi theo bộ lọc (status/kind/q/page). */
    public function costList(Request $request): JsonResponse
    {
        return response()->json(['ok' => true] + $this->svc->costManagementData([
            'status'  => (string) $request->query('status', 'action'),
            'kind'    => (string) $request->query('kind', 'all'),
            'q'       => (string) $request->query('q', ''),
            'page'    => (int) $request->query('page', 1),
            'perPage' => (int) $request->query('perPage', 20),
        ]));
    }

    /** Cập nhật 1 phiếu chi (duyệt/thanh toán/sửa) — trả lại dòng đã cập nhật. */
    public function updateCost(Request $request, TruckingVehicleCost $cost): JsonResponse
    {
        $d = $request->validate([
            'name' => ['nullable', 'string', 'max:255'], 'kind' => ['nullable', 'string'],
            'amount' => ['nullable'], 'spendDate' => ['nullable', 'string'], 'dueDate' => ['nullable', 'string'],
            'currentKm' => ['nullable'], 'supplier' => ['nullable', 'string', 'max:255'], 'note' => ['nullable', 'string'],
            'approved' => ['nullable', 'boolean'], 'paid' => ['nullable', 'boolean'],
            'paidDate' => ['nullable', 'string'], 'paidMethod' => ['nullable', 'string', 'max:64'],
            'paidRef' => ['nullable', 'string', 'max:120'], 'paidNote' => ['nullable', 'string'],
            'photos' => ['nullable', 'array'],
        ]);
        return response()->json($this->svc->updateVehicleCost($cost, $d));
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

    // ---- Theo dõi dầu (phiếu đổ dầu + tiêu thụ) ----

    /** Dữ liệu tab Dầu: phiếu đổ + theo dõi tiêu thụ/còn lại. */
    public function fuelData(TruckingVehicle $vehicle): JsonResponse
    {
        return response()->json(['ok' => true] + $this->svc->fuelTracker($vehicle));
    }

    /** Tạo/sửa phiếu đổ dầu. */
    public function saveFuelRefill(Request $request, TruckingVehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'id'         => ['nullable', 'integer'],
            'date'       => ['required', 'string'],
            'liters'     => ['required'],
            'unitPrice'  => ['nullable'],
            'totalCost'  => ['nullable'],
            'odometerKm' => ['nullable'],
            'station'    => ['nullable', 'string'],
            'note'       => ['nullable', 'string'],
        ]);
        $existing = ! empty($data['id']) ? TruckingFuelRefill::where('vehicle_id', $vehicle->id)->findOrFail($data['id']) : null;
        $this->svc->saveFuelRefill($vehicle, $data, $existing);
        return response()->json(['ok' => true] + $this->svc->fuelTracker($vehicle));
    }

    /** Xóa phiếu đổ dầu. */
    public function deleteFuelRefill(TruckingVehicle $vehicle, TruckingFuelRefill $refill): JsonResponse
    {
        abort_if((int) $refill->vehicle_id !== (int) $vehicle->id, 403);
        $this->svc->deleteFuelRefill($refill);
        return response()->json(['ok' => true] + $this->svc->fuelTracker($vehicle));
    }
}
