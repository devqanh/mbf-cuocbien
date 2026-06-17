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
        // Eager-load tránh 3 lượt lazy query khi statementToArray() truy cập relations.
        $statement->loadMissing(['lines', 'payments', 'customer']);
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
        $statement->loadMissing(['lines', 'payments', 'customer']);
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
        $statement->loadMissing(['lines', 'customer']);
        return response()->json(['ok' => true] + $this->svc->statementReprice($statement));
    }

    /**
     * Đối soát cả danh sách bảng kê (LAZY, gọi sau khi danh sách render) — trả map
     * statementId => {changed} cho các bảng kê có lô lệch phải thu so với snapshot.
     * Dùng để hiện cảnh báo "cần tính lại" ngoài danh sách.
     */
    public function drift(): JsonResponse
    {
        return response()->json(['ok' => true, 'drift' => $this->svc->statementsDrift()]);
    }

    /**
     * Danh sách bảng kê dạng paginate + filter (cho frontend tương lai cần "load more"
     * khi data lớn). Giữ shape per-item như boot.ke → KePage có thể append trực tiếp.
     * Query params: page (>=1), perPage (1..200), customer, from, to (YYYY-MM-DD theo period_to).
     */
    public function list(Request $request): JsonResponse
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = max(1, min(200, (int) $request->query('perPage', 50)));
        $filters = [
            'customer' => (string) $request->query('customer', '') ?: null,
            'from'     => $request->query('from') ?: null,
            'to'       => $request->query('to') ?: null,
        ];
        $offset = ($page - 1) * $perPage;
        $items  = $this->svc->statementsForList($perPage, $offset, $filters);
        $meta   = $this->svc->statementsForListMeta($filters);
        $total  = $meta['total'];
        return response()->json([
            'ok' => true,
            'ke' => $items,
            'meta' => $meta + [
                'page'    => $page,
                'perPage' => $perPage,
                'hasMore' => ($offset + count($items)) < $total,
            ],
        ]);
    }

    /** Xuất Excel theo mẫu chính thức (SNAPSHOT đã lưu — không đọc lô realtime). */
    public function export(TruckingStatement $statement, StatementExcelExporter $exporter): StreamedResponse
    {
        $statement->loadMissing(['lines', 'payments', 'customer']);
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
