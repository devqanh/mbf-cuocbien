<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lộ trình lái xe theo chuyến (ca đêm 20:00 → 08:00 hôm sau) — gom theo biển số xe vào. */
class LoTrinhController extends BaseTruckingController
{
    public function index()
    {
        $drivers = \App\Models\TruckingDriver::orderBy('sort')->orderBy('name')->pluck('name')->filter()->values()->all();
        return view('trucking2.lo-trinh', $this->pageData(['drivers' => $drivers], 'shipments.view', 'shipments.delete'));
    }

    /** JSON: lộ trình của 1 chuyến theo ngày (mặc định hôm nay). */
    public function data(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->format('Y-m-d'));
        return response()->json(['ok' => true] + $this->svc->routeTripByDate($date));
    }

    /** Lưu chi cho lái xe theo ngày + xe (lái nhận + đã chi). */
    public function savePay(Request $request): JsonResponse
    {
        $d = $request->validate([
            'date'   => ['required', 'string'],
            'bks'    => ['required', 'string'],
            'driver' => ['nullable', 'string'],
            'paid'   => ['nullable', 'boolean'],
            'paidDate' => ['nullable', 'string'],
            'note'   => ['nullable', 'string'],
            'extraItems'            => ['nullable', 'array'],
            'extraItems.*.cont'     => ['nullable', 'string'],
            'extraItems.*.name'     => ['nullable', 'string'],
            'extraItems.*.amount'   => ['nullable'],
            'extraItems.*.perDay'   => ['nullable', 'boolean'],
        ]);
        return response()->json($this->svc->saveRoutePay($d['date'], $d['bks'], $d));
    }
}
