<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingTripCostBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Phí xe nội bộ — mô hình KỲ (snapshot): danh sách, tạo/tính/xem, tính lại, CRUD. */
class TripCostController extends BaseTruckingController
{
    /** Danh sách kỳ phí xe. */
    public function index()
    {
        return view('trucking2.phi-xe', $this->pageData([
            'batches' => $this->svc->tripBatchesForList(),
        ], 'tripCost.create', 'tripCost.delete'));
    }

    /** Trang Tạo kỳ phí xe mới. */
    public function create()
    {
        return view('trucking2.phi-xe-tao', $this->pageData([], 'tripCost.create'));
    }

    /** Tính (AJAX): gom lô có giờ xe ra trong kỳ + gợi ý phí. */
    public function compute(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to   = $request->query('to') ?: null;
        return response()->json(['ok' => true] + $this->svc->computeTripCosts($from, $to));
    }

    /** Trang Xem/Sửa 1 kỳ đã lưu (snapshot, nhẹ). */
    public function view(TruckingTripCostBatch $tripCost)
    {
        return view('trucking2.phi-xe-xem', $this->pageData([
            'batch' => $this->svc->tripBatchToArray($tripCost),
        ], 'tripCost.update', 'tripCost.delete'));
    }

    /** Ngữ cảnh "Tính lại" cho kỳ đã lưu (tải lazy khi bấm). */
    public function context(TruckingTripCostBatch $tripCost): JsonResponse
    {
        return response()->json(['ok' => true] + $this->svc->tripBatchContext($tripCost));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['batch' => ['required', 'array']])['batch'];
        $b = $this->svc->saveTripBatch($data);

        return response()->json(['ok' => true, 'batch' => $this->svc->tripBatchToArray($b)]);
    }

    public function update(Request $request, TruckingTripCostBatch $tripCost): JsonResponse
    {
        $data = $request->validate(['batch' => ['required', 'array']])['batch'];
        $b = $this->svc->saveTripBatch($data, $tripCost);

        return response()->json(['ok' => true, 'batch' => $this->svc->tripBatchToArray($b)]);
    }

    public function destroy(TruckingTripCostBatch $tripCost): JsonResponse
    {
        $tripCost->delete();
        return response()->json(['ok' => true]);
    }
}
