<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingPayrollPeriod;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Kỳ LƯƠNG lái xe — mô hình KỲ (snapshot) gom theo BIỂN SỐ XE qua khoảng ngày. */
class TripCostController extends BaseTruckingController
{
    /** Danh sách kỳ lương. */
    public function index()
    {
        return view('trucking2.phi-xe', $this->pageData([
            'batches' => $this->svc->payrollList(),
        ], 'tripCost.create', 'tripCost.delete'));
    }

    /** Trang Tạo kỳ lương mới. */
    public function create()
    {
        return view('trucking2.phi-xe-tao', $this->pageData([], 'tripCost.create'));
    }

    /** Tính (AJAX): gom theo biển số xe qua khoảng ngày (lương phải trả + đã chi theo ngày). */
    public function compute(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: null;
        $to   = $request->query('to') ?: null;
        return response()->json(['ok' => true] + $this->svc->computePayroll($from, $to));
    }

    /** Trang Xem 1 kỳ lương đã lưu (snapshot). */
    public function view(TruckingPayrollPeriod $tripCost)
    {
        return view('trucking2.phi-xe-xem', $this->pageData([
            'batch' => $this->svc->payrollToArray($tripCost),
        ], 'tripCost.update', 'tripCost.delete'));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['batch' => ['required', 'array']])['batch'];
        $b = $this->svc->savePayroll($data);

        return response()->json(['ok' => true, 'batch' => $this->svc->payrollToArray($b)]);
    }

    public function update(Request $request, TruckingPayrollPeriod $tripCost): JsonResponse
    {
        $data = $request->validate(['batch' => ['required', 'array']])['batch'];
        $b = $this->svc->savePayroll($data, $tripCost);

        return response()->json(['ok' => true, 'batch' => $this->svc->payrollToArray($b)]);
    }

    public function destroy(TruckingPayrollPeriod $tripCost): JsonResponse
    {
        $tripCost->delete();
        return response()->json(['ok' => true]);
    }
}
