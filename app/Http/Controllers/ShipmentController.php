<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Models\Shipment;
use App\Services\PayableReportService;
use App\Services\ShipmentExportService;
use App\Services\ShipmentService;
use App\Services\SheetSnapshotService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly SheetSnapshotService $snapshots,
        private readonly PayableReportService $payable,
        private readonly ShipmentExportService $exporter,
    ) {}

    /** Export shipments của 1 period sang file XLSX (download). */
    public function export(string $period, Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $file = $this->exporter->exportPeriod($period, $user);
        $filename = "shipments_{$period}_" . now()->format('Ymd_His') . ".xlsx";

        return response()->download($file, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /** /shipments → redirect sang tháng MỚI NHẤT (max trong DB). */
    public function redirectToCurrent(): RedirectResponse
    {
        return redirect()->route('shipments.show', ['period' => $this->shipments->latestPeriod()]);
    }

    /** Trang theo dõi 1 tháng cụ thể. */
    public function show(string $period, Request $request)
    {
        $user = $request->user();
        $cols = config('shipment_columns', []);

        // Build columnPerms map: { key => 'hidden'|'view'|'edit' }
        $perms = [];
        foreach ($cols as $col) {
            $perms[$col['key']] = $user->columnPermission($col['key']);
        }

        // List NCC (suppliers) — gộp từ payable_initial_balances + distinct supplier trong shipments
        // Lọc bỏ tên chứa dấu phẩy (vì Luckysheet dùng `,` làm separator trong dropdown)
        $suppliers = $this->payable->availableSuppliers()
            ->filter(fn ($s) => ! str_contains($s, ','))
            ->values()
            ->all();

        return view('shipments.index', [
            'period'      => $period,
            'periods'     => $this->shipments->listPeriods(),
            'current'     => $this->shipments->currentPeriod(),
            'columns'     => $cols,
            'columnPerms' => $perms,
            'userPrefs'   => $user->shipment_column_prefs ?? [],
            'suppliers'   => $suppliers,
            'canDelete'   => $user->can('shipments.delete'),
        ]);
    }

    /** Lưu cột user tự chọn ẩn (preference cá nhân). */
    public function updateColumnPrefs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hidden'   => ['array'],
            'hidden.*' => ['string'],
        ]);

        // Chỉ giữ key thuộc danh sách cột hợp lệ
        $validKeys = array_column(config('shipment_columns', []), 'key');
        $hidden = array_values(array_intersect($data['hidden'] ?? [], $validKeys));

        $user = $request->user();
        $user->shipment_column_prefs = $hidden ?: null;
        $user->save();

        return response()->json(['ok' => true, 'hidden' => $hidden]);
    }

    /** API: dữ liệu của 1 tháng (Nhập + Xuất + formatting overlay). */
    public function data(string $period, Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = $this->shipments->listForGrid($period, $user);

        // Snapshot payload giờ chỉ chứa formatting overlay (small) — load nhẹ.
        $snap = $this->snapshots->get($this->shipments->sheetKey($period));

        return response()->json([
            'period'    => $period,
            'data'      => [
                'import' => $rows['import'],
                'export' => $rows['export'],
            ],
            'version'   => $snap?->version ?? 0,
            'editor'    => $snap?->editor?->only(['id', 'name']),
            'updatedAt' => $snap?->updated_at?->toIso8601String(),
            'snapshot'  => $snap?->payload,
        ]);
    }

    /** Tạo tháng mới (snapshot rỗng đánh dấu period tồn tại). */
    public function createPeriod(Request $request): JsonResponse
    {
        $data = $request->validate([
            'period' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
        ], ['period.regex' => 'Định dạng tháng phải là YYYY-MM (vd: 2026-06).']);

        try {
            $period = $this->shipments->createPeriod($data['period'], $request->user()->id);
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()] + $e->context(), $e->httpStatus());
        }

        return response()->json(['ok' => true, 'period' => $period]);
    }

    public function store(Request $request, string $period, string $direction): JsonResponse
    {
        $data = $this->validateData($request);
        $shipment = $this->shipments->create($period, $direction, $data);

        return response()->json(['ok' => true, 'item' => $shipment], 201);
    }

    public function update(Request $request, Shipment $shipment): JsonResponse
    {
        $data = $this->validateData($request);
        $shipment = $this->shipments->update($shipment, $data);

        return response()->json(['ok' => true, 'item' => $shipment]);
    }

    public function destroy(Shipment $shipment): JsonResponse
    {
        $this->shipments->delete($shipment);
        return response()->json(['ok' => true]);
    }

    public function bulk(Request $request, string $period): JsonResponse
    {
        // Snapshot validation — KHÔNG dùng wildcard 'snapshot.*.celldata' vì Laravel
        // wildcard validation strip assoc keys (formatting, formatting_scope) thành
        // numeric array [item0, item1] → mất structure → backend không nhận đúng.
        // Dùng rules explicit cho cấu trúc mới.
        $payload = $request->validate([
            'rows'                          => ['required', 'array'],
            'rows.import'                   => ['array',  'max:5000'],
            'rows.export'                   => ['array',  'max:5000'],
            'deleted_ids'                   => ['nullable', 'array', 'max:5000'],
            'deleted_ids.*'                 => ['integer'],
            'snapshot'                      => ['nullable', 'array'],
            'snapshot.formatting'           => ['nullable', 'array'],
            'snapshot.formatting.import'    => ['nullable', 'array'],
            'snapshot.formatting.export'    => ['nullable', 'array'],
            'snapshot.formatting_scope'     => ['nullable', 'array'],
            'snapshot.columnlen'            => ['nullable', 'array'],
            'snapshot.columnlen.import'    => ['nullable', 'array'],
            'snapshot.columnlen.export'    => ['nullable', 'array'],
            'client_version'                => ['nullable', 'integer'],
        ], [
            'rows.import.max' => 'Quá nhiều dòng HÀNG NHẬP (tối đa 5000).',
            'rows.export.max' => 'Quá nhiều dòng HÀNG XUẤT (tối đa 5000).',
        ]);

        try {
            $result = $this->shipments->bulkSave(
                period:        $period,
                rowsByDirection: $payload['rows'],
                snapshot:      $payload['snapshot']       ?? null,
                clientVersion: $payload['client_version'] ?? 0,
                editor:        $request->user(),
                deletedIds:    $payload['deleted_ids']    ?? [],
            );
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()] + $e->context(), $e->httpStatus());
        }

        // DEBUG: read back snapshot ngay sau save để confirm backend đã persist gì
        $snapAfter = $this->snapshots->get($this->shipments->sheetKey($period));
        $debugSnap = [
            'exists'          => $snapAfter !== null,
            'version'         => $snapAfter?->version,
            'payload_type'    => $snapAfter ? gettype($snapAfter->payload) : null,
            'payload_keys'    => $snapAfter && is_array($snapAfter->payload) ? array_keys($snapAfter->payload) : null,
            'has_formatting'  => $snapAfter && is_array($snapAfter->payload) && isset($snapAfter->payload['formatting']),
            'fmt_import_n'    => $snapAfter && isset($snapAfter->payload['formatting']['import']) ? count($snapAfter->payload['formatting']['import']) : null,
            'fmt_export_n'    => $snapAfter && isset($snapAfter->payload['formatting']['export']) ? count($snapAfter->payload['formatting']['export']) : null,
            'first_import'    => $snapAfter && ! empty($snapAfter->payload['formatting']['import']) ? $snapAfter->payload['formatting']['import'][0] : null,
        ];

        return response()->json(['ok' => true, 'period' => $period, '_debug_snapshot' => $debugSnap, ...$result]);
    }

    public function resetSnapshot(string $period): JsonResponse
    {
        $this->shipments->resetSnapshot($period);
        return response()->json(['ok' => true]);
    }

    private function validateData(Request $request): array
    {
        $text = ['nullable', 'string', 'max:255'];
        $shortText = ['nullable', 'string', 'max:128'];
        $code  = ['nullable', 'string', 'max:64'];
        $date  = ['nullable', 'date'];
        $money = ['nullable', 'numeric', 'min:0'];

        return $request->validate([
            // Core
            'client'         => ['required', 'string', 'max:255'],
            'hbl'            => $code,
            'mbl_no'         => $code,
            'bkg_no'         => $code,
            'pol'            => $code,
            'pod'            => $code,
            'vol'            => $code,
            'container_type' => ['nullable', 'string', 'max:32'],
            'etd'            => $date,
            'eta'            => $date,
            'vessel_name'    => $text,
            'line'           => $code,
            'note'           => ['nullable', 'string'],

            // Status (8) — text
            'vgm'           => ['nullable', 'string', 'max:100'],
            'si'            => ['nullable', 'string', 'max:100'],
            'bl_draft'      => ['nullable', 'string', 'max:100'],
            'bl_confirm'    => ['nullable', 'string', 'max:100'],
            'obl'           => ['nullable', 'string', 'max:100'],
            'tlx'           => ['nullable', 'string', 'max:100'],
            'swb'           => ['nullable', 'string', 'max:100'],
            'shipment_done' => ['nullable', 'string', 'max:100'],

            // Mua / NCC
            'purchase_note'         => ['nullable', 'string'],
            'payment_amount'        => $money,
            'supplier'              => $shortText,
            'supplier_due_date'           => $date,
            'report_close_date_increase'  => $date,
            'report_close_date_decrease'  => $date,
            'supplier_paid_date'          => $date,
            'cost_recognized'       => $money,
            'trucking_cost'         => $money,
            'purchase_invoice_no'   => $code,
            'purchase_invoice_date' => $date,

            // Agent (payable)
            'driver_hoa'      => $shortText,
            'agent_fee'       => $money,
            'agent_name'      => $shortText,
            'agent_fee_vnd'   => $money,
            'agent_due_date'  => $date,
            'agent_paid_date' => $date,

            // Agent receivable (credit note)
            'credit_note_agent'         => $money,
            'agent_receivable_amount'   => $money,
            'credit_note_agent_vnd'     => $money,
            'agent_receivable_due_date' => $date,
            'agent_received_amount'     => $money,
            'agent_received_date'       => $date,

            // Bán / KH
            'sale_note'           => ['nullable', 'string'],
            'receivable_amount'   => $money,
            'customer'            => $shortText,
            'received_amount'     => $money,
            'receivable_due_date' => $date,
            'received_date'       => $date,
            'revenue_recognized'  => $money,
            'sale_invoice_no'     => $code,
            'sale_invoice_date'   => $date,
        ]);
    }
}
