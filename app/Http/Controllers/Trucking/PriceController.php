<?php

namespace App\Http\Controllers\Trucking;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bảng giá theo khách — nhiều BẢNG GIÁ (price book) theo khoảng ngày; lazy-load giá 1 book; import/copy/CRUD book. */
class PriceController extends BaseTruckingController
{
    /** Trang Bảng giá. */
    public function index()
    {
        return view('trucking2.bang-gia', $this->pageData(['cfg' => $this->svc->priceBookConfig()], 'prices.update', 'prices.update'));
    }

    /** Dòng giá của 1 BOOK (lazy-load để sửa). */
    public function customerPrices(Request $request): JsonResponse
    {
        $bookId = (int) $request->query('book', 0);
        return response()->json(['ok' => true, 'priceList' => $this->svc->priceBookRows($bookId)]);
    }

    /** Danh sách bảng giá (book) của 1 khách. */
    public function books(Request $request): JsonResponse
    {
        $customerId = (int) $request->query('customerId', 0);
        return response()->json(['ok' => true, 'books' => $this->svc->priceBooksForCustomer($customerId)]);
    }

    /** Tạo bảng giá mới (khoảng ngày) cho 1 khách. */
    public function createBook(Request $request): JsonResponse
    {
        $d = $request->validate([
            'customer' => ['required', 'string'],
            'label'    => ['nullable', 'string'],
            'from'     => ['nullable', 'date'],
            'to'       => ['nullable', 'date'],
        ]);
        return response()->json(['ok' => true] + $this->svc->createPriceBook($d['customer'], $d['label'] ?? null, $d['from'] ?? null, $d['to'] ?? null));
    }

    /** Sửa khoảng ngày / nhãn của 1 bảng giá. */
    public function updateBook(Request $request, int $book): JsonResponse
    {
        $d = $request->validate([
            'label' => ['nullable', 'string'],
            'from'  => ['nullable', 'date'],
            'to'    => ['nullable', 'date'],
        ]);
        return response()->json($this->svc->updatePriceBook($book, $d['label'] ?? null, $d['from'] ?? null, $d['to'] ?? null));
    }

    /** Xóa 1 bảng giá (kèm dòng giá của nó). */
    public function deleteBook(int $book): JsonResponse
    {
        return response()->json($this->svc->deletePriceBook($book));
    }

    /** Lưu toàn bộ dòng giá của 1 BOOK (xóa-hết-tạo-lại trong phạm vi book). */
    public function saveBookRows(Request $request, int $book): JsonResponse
    {
        $d = $request->validate(['rows' => ['present', 'array']]);
        return response()->json($this->svc->savePriceBookRows($book, $d['rows']));
    }

    /** Import bảng giá vào 1 BOOK từ Excel (dòng đã parse client). */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer' => ['required', 'string'],
            'book'     => ['nullable', 'integer'],
            'rows'     => ['present', 'array'],
            'replace'  => ['nullable', 'boolean'],
        ]);
        $res = $this->svc->importPriceRows($data['customer'], $data['rows'], (bool) ($data['replace'] ?? false), $data['book'] ?? null);

        return response()->json(['ok' => true] + $res);
    }

    /** Copy dòng giá từ 1 BOOK khác sang BOOK đang chọn. */
    public function copy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fromBook' => ['required', 'integer'],
            'toBook'   => ['required', 'integer'],
            'replace'  => ['nullable', 'boolean'],
        ]);
        $res = $this->svc->copyPriceRows((int) $data['fromBook'], (int) $data['toBook'], (bool) ($data['replace'] ?? false));

        return response()->json(['ok' => true] + $res);
    }
}
