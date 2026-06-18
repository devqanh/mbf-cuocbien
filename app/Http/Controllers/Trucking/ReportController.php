<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Báo cáo chi phí công ty theo tháng (P&L + cơ cấu chi phí + chi phí theo xe). */
class ReportController extends BaseTruckingController
{
    public function index()
    {
        $now = now();
        return view('trucking2.bao-cao', $this->pageData([
            'report' => $this->svc->monthlyCostReport((int) $now->year, (int) $now->month),
        ], 'tripCost.view'));
    }

    /** JSON: báo cáo 1 tháng (year, month). */
    public function data(Request $request): JsonResponse
    {
        $d = $request->validate([
            'year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        return response()->json(['ok' => true, 'report' => $this->svc->monthlyCostReport((int) $d['year'], (int) $d['month'])]);
    }

    /** JSON: xu hướng 12 tháng (kết tại year/month) — lazy-load vì có cộng route-pay theo ngày. */
    public function trend(Request $request): JsonResponse
    {
        $d = $request->validate([
            'year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        return response()->json(['ok' => true] + $this->svc->costTrend((int) $d['year'], (int) $d['month']));
    }
}
