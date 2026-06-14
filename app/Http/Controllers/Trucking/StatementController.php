<?php

namespace App\Http\Controllers\Trucking;

use App\Models\TruckingStatement;
use App\Services\Trucking\StatementExcelExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Bảng kê cần thu — danh sách, tạo/xem, đối soát (context), xuất Excel, CRUD. */
class StatementController extends BaseTruckingController
{
    /** Trang Bảng kê — gom lô theo khách + kỳ, theo dõi công nợ. */
    public function index()
    {
        return view('trucking2.bang-ke', $this->pageData([
            'ke' => $this->svc->statementsForList(),
        ], 'statements.update', 'statements.delete'));
    }

    /** Trang Tạo bảng kê mới — chỉ boot khách + thông tin khách (KHÔNG nạp toàn bộ lô/bảng giá).
     *  Lô + định giá lấy lazy qua endpoint candidates (so khớp ở backend). */
    public function create()
    {
        return view('trucking2.bang-ke-tao', $this->pageData([
            'cfg' => $this->svc->config(withPrices: false),   // khách + customerInfo, không kèm priceList nặng
            'company' => $this->svc->companyInfo(),
        ], 'statements.create', 'statements.delete'));
    }

    /** Trang Xem bảng kê đã lưu — chỉ nạp bảng kê (nhẹ). Đối soát/bảng giá tải lazy. */
    public function view(TruckingStatement $statement)
    {
        return view('trucking2.bang-ke-xem', $this->pageData([
            'st' => $this->svc->statementToArray($statement),
            'company' => $this->svc->companyInfo(),
        ], 'statements.update', 'statements.delete'));
    }

    /**
     * Ngữ cảnh định giá cho 1 bảng kê (tải lazy khi cần đối soát/tính lại):
     * chỉ lô có trong bảng kê + bảng giá của đúng khách → tránh nạp toàn bộ.
     */
    public function context(TruckingStatement $statement): JsonResponse
    {
        $st  = $this->svc->statementToArray($statement);
        $ids = array_filter(array_map(fn ($l) => $l['id'] ?? null, $st['lines'] ?? []));

        return response()->json([
            'ok'    => true,
            'cfg'   => $this->svc->pricingCfg($st['customer'] ?? null),
            'ships' => $this->svc->shipmentsByIds($ids),
        ]);
    }

    /** Ứng viên cho bảng kê MỚI — lô của 1 khách trong khoảng cont-ra, ĐÃ định giá ở backend. */
    public function candidates(Request $request): JsonResponse
    {
        $customer = (string) $request->query('customer', '');
        $from = $request->query('from') ?: null;
        $to   = $request->query('to') ?: null;
        return response()->json(['ok' => true] + $this->svc->statementCandidates($customer, $from, $to));
    }

    /** Tính lại bảng kê đã lưu — định giá lại ở backend theo dữ liệu hiện tại. */
    public function reprice(TruckingStatement $statement): JsonResponse
    {
        return response()->json(['ok' => true] + $this->svc->statementReprice($statement));
    }

    /** Xuất Excel theo mẫu chính thức (SNAPSHOT đã lưu — không đọc lô realtime). */
    public function export(TruckingStatement $statement, StatementExcelExporter $exporter): StreamedResponse
    {
        return $exporter->download($this->svc->statementToArray($statement), $this->svc->sellerInfo());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'array']])['statement'];
        $st = $this->svc->saveStatement($data);

        return response()->json(['ok' => true, 'statement' => $this->svc->statementToArray($st)]);
    }

    public function update(Request $request, TruckingStatement $statement): JsonResponse
    {
        $data = $request->validate(['statement' => ['required', 'array']])['statement'];
        $st = $this->svc->saveStatement($data, $statement);

        return response()->json(['ok' => true, 'statement' => $this->svc->statementToArray($st)]);
    }

    public function destroy(TruckingStatement $statement): JsonResponse
    {
        $statement->delete();
        return response()->json(['ok' => true]);
    }
}
