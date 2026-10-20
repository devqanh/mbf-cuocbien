<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Lộ trình lái xe theo chuyến (ca đêm 20:00 → 08:00 hôm sau) — gom theo biển số xe vào. */
class LoTrinhController extends BaseTruckingController
{
    public function index()
    {
        // Lái xe + tài khoản NH (bank/number/holder/bin) → popup "chi cho lái" dựng được QR VietQR.
        $drivers = \App\Models\TruckingDriver::orderBy('sort')->orderBy('name')->get()
            ->filter(fn ($d) => trim((string) $d->name) !== '')
            ->map(fn ($d) => [
                'name'  => $d->name,
                'banks' => is_array($d->bank_accounts) ? array_values($d->bank_accounts) : [],
            ])->values()->all();
        // canEdit = quyền chi cho lái / chốt ngày (driverPay.manage) — tách khỏi quyền sửa lô hàng.
        return view('trucking2.lo-trinh', $this->pageData(['drivers' => $drivers], 'driverPay.manage', 'driverPay.manage'));
    }

    /** JSON: lộ trình của 1 chuyến theo ngày (mặc định hôm nay). */
    public function data(Request $request): JsonResponse
    {
        $date = (string) $request->query('date', now()->format('Y-m-d'));
        // Trang Lộ trình hiện thêm chuyến đã gán xe nhưng chưa ra (chưa hoàn thành) — chỉ để xem, không tính tiền.
        return response()->json(['ok' => true] + $this->svc->routeTripByDate($date, true));
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
            'extraItems.*.choHo'    => ['nullable', 'boolean'],
            'extraItems.*.khac'     => ['nullable', 'boolean'],   // chi phí khác (không tính lương, thống kê riêng)
            'extraItems.*.note'     => ['nullable', 'string', 'max:500'],   // ghi chú chi phí khác
            'chohoItems'            => ['nullable', 'array'],    // khoản phí tuyến chọn "Chi hộ" theo chuyến: [{cont, keys[]}]
            'chohoItems.*.cont'     => ['nullable', 'string'],
            'chohoItems.*.keys'     => ['nullable', 'array'],
            'amountItems'           => ['nullable', 'array'],    // số tiền nhập tay theo chuyến: [{cont, key, amount}]
            'amountItems.*.cont'    => ['nullable', 'string'],
            'amountItems.*.key'     => ['required', 'string', 'max:40'],
            'amountItems.*.amount'  => ['nullable'],
        ]);
        return response()->json($this->svc->saveRoutePay($d['date'], $d['bks'], $d));
    }

    /** Sửa lít dầu 1 chuyến (cont) của xe trong ngày; liters rỗng = về định mức phí tuyến. Nhận "12,4" hoặc 12.4. */
    public function saveFuel(Request $request): JsonResponse
    {
        $d = $request->validate([
            'date'   => ['required', 'string'],
            'bks'    => ['required', 'string'],
            'cont'   => ['nullable', 'string'],
            'liters' => ['nullable'],
        ]);
        $raw = trim(str_replace(',', '.', (string) ($d['liters'] ?? '')));
        if ($raw !== '' && ! is_numeric($raw)) return response()->json(['ok' => false, 'message' => 'Số lít không hợp lệ'], 422);
        return response()->json($this->svc->saveRouteFuel($d['date'], $d['bks'], (string) ($d['cont'] ?? ''), $raw === '' ? null : (float) $raw));
    }

    /** Sửa tay phụ cấp đi đường / lương chuyến của 1 chuyến ngay trên Lộ trình (amount trống = về mức quy chế). */
    public function saveAmount(Request $request): JsonResponse
    {
        $d = $request->validate([
            'date'   => ['required', 'string'],
            'bks'    => ['required', 'string'],
            'cont'   => ['nullable', 'string'],
            'key'    => ['required', 'string', 'in:tienDuong,luong'],
            'amount' => ['nullable'],
        ]);
        $raw = preg_replace('/\D/', '', (string) ($d['amount'] ?? ''));
        return response()->json($this->svc->saveRouteAmount($d['date'], $d['bks'], (string) ($d['cont'] ?? ''), $d['key'], $raw === '' ? null : (int) $raw));
    }

    /** Chốt / bỏ chốt cả ngày (đóng băng số tiền chi cho lái). */
    public function freeze(Request $request): JsonResponse
    {
        $d = $request->validate([
            'date'   => ['required', 'string'],
            'frozen' => ['required', 'boolean'],
        ]);
        return response()->json($this->svc->freezeDay($d['date'], (bool) $d['frozen']));
    }
}
