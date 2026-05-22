<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Models\Shipment;
use App\Services\ShipmentService;
use App\Services\SheetSnapshotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function __construct(
        private readonly ShipmentService $shipments,
        private readonly SheetSnapshotService $snapshots,
    ) {}

    /** /shipments → redirect sang tháng hiện tại. */
    public function redirectToCurrent(): RedirectResponse
    {
        return redirect()->route('shipments.show', ['period' => $this->shipments->currentPeriod()]);
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

        return view('shipments.index', [
            'period'      => $period,
            'periods'     => $this->shipments->listPeriods(),
            'current'     => $this->shipments->currentPeriod(),
            'columns'     => $cols,
            'columnPerms' => $perms,
            'userPrefs'   => $user->shipment_column_prefs ?? [],   // list of hidden col keys (user preference)
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

    /** API: dữ liệu của 1 tháng (cả Nhập + Xuất + snapshot). */
    public function data(string $period, Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = $this->shipments->listForGrid($period, $user);

        $summary = $this->snapshots->summary($this->shipments->sheetKey($period));
        $summary['snapshot'] = $this->shipments->filterSnapshotForUser($summary['snapshot'], $user);

        return response()->json([
            'period' => $period,
            'data'   => [
                'import' => $rows['import'],
                'export' => $rows['export'],
            ],
            ...$summary,
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
        $payload = $request->validate([
            'rows'           => ['required', 'array'],
            'rows.import'    => ['array'],
            'rows.export'    => ['array'],
            'snapshot'       => ['nullable', 'array'],
            'client_version' => ['nullable', 'integer'],
        ]);

        try {
            $result = $this->shipments->bulkSave(
                period:        $period,
                rowsByDirection: $payload['rows'],
                snapshot:      $payload['snapshot']       ?? null,
                clientVersion: $payload['client_version'] ?? 0,
                editor:        $request->user(),
            );
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()] + $e->context(), $e->httpStatus());
        }

        return response()->json(['ok' => true, 'period' => $period, ...$result]);
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

            // Agent
            'driver_hoa'      => $shortText,
            'agent_fee'       => $money,
            'agent_name'      => $shortText,
            'agent_fee_vnd'   => $money,
            'agent_due_date'  => $date,
            'agent_paid_date' => $date,

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
