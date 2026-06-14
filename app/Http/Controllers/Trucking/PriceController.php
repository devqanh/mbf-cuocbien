<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bảng giá theo khách — trang, lazy-load giá 1 khách, import từ Excel. */
class PriceController extends BaseTruckingController
{
    /** Trang Bảng giá — bảng giá đã gửi theo từng khách. */
    public function index()
    {
        // Bảng giá chỉ cần khách + địa điểm; bảng giá từng khách lazy-load, danh mục khác bỏ.
        return view('trucking2.bang-gia', $this->pageData(['cfg' => $this->svc->priceBookConfig()], 'prices.update', 'prices.update'));
    }

    /** Bảng giá của 1 khách (lazy-load). */
    public function customerPrices(Request $request): JsonResponse
    {
        $name = (string) $request->query('customer', '');
        return response()->json(['ok' => true, 'priceList' => $this->svc->customerPriceList($name)]);
    }

    /** Import bảng giá 1 khách từ Excel (dòng đã parse phía client). */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer' => ['required', 'string'],
            'rows'     => ['present', 'array'],
            'replace'  => ['nullable', 'boolean'],
        ]);
        $res = $this->svc->importPriceRows($data['customer'], $data['rows'], (bool) ($data['replace'] ?? false));

        return response()->json(['ok' => true] + $res);
    }
}
