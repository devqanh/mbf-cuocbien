<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingExtStatement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Bảng kê xe ngoài (phải trả nhà xe thuê) — danh sách, tạo/xem, CRUD. */
class ExtStatementController extends BaseTruckingController
{
    /** Trang Bảng kê xe ngoài — gom lô theo nhà xe + kỳ, theo dõi công nợ phải trả. */
    public function index()
    {
        return view('trucking2.bang-ke-xe-ngoai', $this->pageData([
            'ke'     => $this->svc->extStatementsForList(),
            'alerts' => $this->svc->extStatementAlerts(),   // cảnh báo cho kế toán (chưa lên bảng kê, trùng, lệch, quá hạn…)
        ], 'extStatements.update', 'extStatements.delete'));
    }

    /** Trang Tạo bảng kê xe ngoài — boot danh mục nhà xe (cfg.extVendors). */
    public function create()
    {
        return view('trucking2.bang-ke-xe-ngoai-tao', $this->pageData([
            'cfg' => $this->svc->config(withPrices: false),
        ], 'extStatements.create', 'extStatements.delete'));
    }

    /** Trang Xem bảng kê xe ngoài đã lưu. */
    public function view(TruckingExtStatement $extStatement)
    {
        $extStatement->loadMissing(['lines', 'payments']);
        return view('trucking2.bang-ke-xe-ngoai-xem', $this->pageData([
            'st' => $this->svc->extStatementToArray($extStatement),
        ], 'extStatements.update', 'extStatements.delete'));
    }

    /** Ứng viên cho bảng kê MỚI — lô của 1 nhà xe trong khoảng Giờ xe đến, ext_fee > 0. */
    public function candidates(Request $request): JsonResponse
    {
        $vendor = (string) $request->query('vendor', '');
        $from = $request->query('from') ?: null;
        $to   = $request->query('to') ?: null;
        return response()->json(['ok' => true] + $this->svc->extStatementCandidates($vendor, $from, $to));
    }

    /** Danh sách bảng kê xe ngoài (JSON) — filter optional vendor/from/to. */
    public function list(Request $request): JsonResponse
    {
        $filters = [
            'vendor' => (string) $request->query('vendor', '') ?: null,
            'from'   => $request->query('from') ?: null,
            'to'     => $request->query('to') ?: null,
        ];
        return response()->json(['ok' => true, 'ke' => $this->svc->extStatementsForList($filters)]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'array']])['statement'];
        $st = $this->svc->saveExtStatement($data);
        return response()->json(['ok' => true, 'statement' => $this->svc->extStatementToArray($st)]);
    }

    public function update(Request $request, TruckingExtStatement $extStatement): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'array']])['statement'];
        $st = $this->svc->saveExtStatement($data, $extStatement);
        return response()->json(['ok' => true, 'statement' => $this->svc->extStatementToArray($st)]);
    }

    /** Tích / bỏ tích "đã chi tiền" cho các dòng lô ngay trên trang xem. */
    public function setLinesPaid(Request $request, TruckingExtStatement $extStatement): JsonResponse
    {
        $d = $request->validate([
            'lineIds'   => ['required', 'array'],
            'lineIds.*' => ['integer'],
            'paid'      => ['required', 'boolean'],
            'date'      => ['nullable', 'date_format:Y-m-d'],
            'part'      => ['nullable', 'string', 'max:64'],   // null = cả dòng; 'fee' | 'i:<key>' = 1 phần
        ]);
        return response()->json(['ok' => true, 'paid' => $this->svc->setExtStatementLinesPaid($extStatement, $d['lineIds'], (bool) $d['paid'], $d['date'] ?? null, $d['part'] ?? null)]);
    }

    public function destroy(TruckingExtStatement $extStatement): JsonResponse
    {
        $extStatement->delete();
        return response()->json(['ok' => true]);
    }
}
