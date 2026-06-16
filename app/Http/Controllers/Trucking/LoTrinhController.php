<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lộ trình lái xe theo chuyến (ca đêm 20:00 → 08:00 hôm sau) — gom theo biển số xe vào. */
class LoTrinhController extends BaseTruckingController
{
    public function index()
    {
        return view('trucking2.lo-trinh', $this->pageData([], 'shipments.view', 'shipments.delete'));
    }

    /** JSON: lộ trình của 1 chuyến theo ngày (mặc định hôm nay). */
    public function data(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->format('Y-m-d'));
        return response()->json(['ok' => true] + $this->svc->routeTripByDate($date));
    }
}
