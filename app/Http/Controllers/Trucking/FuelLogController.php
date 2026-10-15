<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingFuelRefill;
use App\Models\TruckingVehicle;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Nhật ký đổ dầu — phiếu đổ dầu của mọi xe MBF trên 1 trang (cùng dữ liệu tab Dầu ở Quản lý xe). */
class FuelLogController extends BaseTruckingController
{
    public function index()
    {
        return view('trucking2.nhat-ky-do-dau', $this->pageData($this->svc->fuelLogBoot()));
    }

    /** JSON phân trang + lọc (from, to, vehicles[], q) + tổng hợp theo xe. */
    public function data(Request $request): JsonResponse
    {
        return response()->json(['ok' => true] + $this->svc->fuelLogData(
            $request->only(['from', 'to', 'vehicles', 'q']),
            (int) $request->query('page', 1),
            (int) $request->query('perPage', 50),
        ));
    }

    /** Tạo / sửa 1 phiếu đổ dầu (đổi được cả xe khi sửa). */
    public function save(Request $request): JsonResponse
    {
        $data = $request->validate([
            'id'         => ['nullable', 'integer'],
            'vehicleId'  => ['required', 'integer'],
            'date'       => ['required', 'date'],
            'liters'     => ['required', 'numeric', 'gt:0', 'max:100000'],
            'unitPrice'  => ['nullable'],
            'totalCost'  => ['nullable'],
            'odometerKm' => ['nullable'],
            'station'    => ['nullable', 'string', 'max:255'],
            'note'       => ['nullable', 'string', 'max:2000'],
        ], [
            'vehicleId.required' => 'Chưa chọn biển số xe.',
            'liters.gt'          => 'Số lít phải lớn hơn 0.',
            'liters.numeric'     => 'Số lít không hợp lệ.',
        ]);
        $vehicle = TruckingVehicle::mbfFleet()->findOrFail($data['vehicleId']);
        $existing = ! empty($data['id']) ? TruckingFuelRefill::findOrFail($data['id']) : null;
        $refill = $this->svc->saveFuelRefill($vehicle, $data, $existing);
        return response()->json(['ok' => true, 'id' => $refill->id]);
    }

    public function destroy(TruckingFuelRefill $refill): JsonResponse
    {
        $this->svc->deleteFuelRefill($refill);
        return response()->json(['ok' => true]);
    }
}
