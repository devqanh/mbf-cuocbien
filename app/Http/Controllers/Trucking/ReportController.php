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
        ], 'reports.view'));
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

    /** Trang Báo cáo tài sản — thống kê theo từng xe/tài sản (chi phí · phân bổ · khấu hao) theo khoảng tháng. */
    public function assetIndex()
    {
        $ym = now()->format('Y-m');
        return view('trucking2.bao-cao-tai-san', $this->pageData([
            'report' => $this->svc->assetReport($ym, $ym),
            'years'  => $this->svc->assetReportYears(),   // năm có dữ liệu → chọn Tháng + Năm
        ], 'reports.view'));
    }

    /** JSON: báo cáo tài sản theo khoảng tháng (from, to = YYYY-MM). */
    public function assetData(Request $request): JsonResponse
    {
        $d = $request->validate([
            'from' => ['required', 'string', 'regex:/^\d{4}-\d{1,2}$/'],
            'to'   => ['required', 'string', 'regex:/^\d{4}-\d{1,2}$/'],
        ]);
        return response()->json(['ok' => true, 'report' => $this->svc->assetReport($d['from'], $d['to'])]);
    }

    /** Trang Báo cáo lái xe — số chuyến theo lái xe trong khoảng ngày (dựng từ Lộ trình). */
    public function driverIndex()
    {
        return view('trucking2.bao-cao-lai-xe', $this->pageData([
            'report' => $this->svc->driverTripReport(now()->startOfMonth()->format('Y-m-d'), now()->format('Y-m-d')),
        ], 'reports.view'));
    }

    /** JSON: báo cáo lái xe theo khoảng ngày (from, to = YYYY-MM-DD). */
    public function driverData(Request $request): JsonResponse
    {
        $d = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to'   => ['required', 'date_format:Y-m-d'],
        ]);
        return response()->json(['ok' => true, 'report' => $this->svc->driverTripReport($d['from'], $d['to'])]);
    }

    /** Trang Báo cáo công nợ xe ngoài — dư đầu kỳ / phát sinh tăng / phát sinh giảm / dư cuối kỳ theo nhà xe, theo tháng. */
    public function extPayableIndex()
    {
        $now = now();
        return view('trucking2.bao-cao-xe-ngoai', $this->pageData([
            'report' => $this->svc->extPayableReport((int) $now->year, (int) $now->month),
        ], 'reports.view'));
    }

    /** JSON: báo cáo công nợ xe ngoài 1 tháng (year, month). */
    public function extPayableData(Request $request): JsonResponse
    {
        $d = $request->validate([
            'year'  => ['required', 'integer', 'min:2000', 'max:2100'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
        ]);
        return response()->json(['ok' => true, 'report' => $this->svc->extPayableReport((int) $d['year'], (int) $d['month'])]);
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
