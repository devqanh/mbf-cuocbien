<?php

namespace App\Http\Controllers;

use App\Exceptions\Domain\DomainException;
use App\Services\PayableReportService;
use App\Services\SheetSnapshotService;
use App\Services\TruckingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TruckingController extends Controller
{
    public function __construct(
        private readonly TruckingService $trucking,
        private readonly SheetSnapshotService $snapshots,
        private readonly PayableReportService $payable,
    ) {}

    /** Trang Trucking — 2 sheet HẠ HPH + HẠ ICD. */
    public function index(Request $request)
    {
        $cols = config('trucking_columns', []);

        // Danh sách NCC cho dropdown (loại tên có dấu phẩy — Luckysheet dùng ',' separator)
        $suppliers = $this->payable->availableSuppliers()
            ->filter(fn ($s) => ! str_contains($s, ','))
            ->values()
            ->all();

        return view('trucking.index', [
            'colsHph'   => $cols['hph'] ?? [],
            'colsIcd'   => $cols['icd'] ?? [],
            'suppliers' => $suppliers,
            'canDelete' => $request->user()->can('shipments.delete'),
        ]);
    }

    /** API: dữ liệu 2 sheet + formatting snapshot. */
    public function data(): JsonResponse
    {
        $rows = $this->trucking->listForGrid();
        $snap = $this->snapshots->get($this->trucking->sheetKey());

        return response()->json([
            'data'      => ['hph' => $rows['hph'], 'icd' => $rows['icd']],
            'version'   => $snap?->version ?? 0,
            'editor'    => $snap?->editor?->only(['id', 'name']),
            'updatedAt' => $snap?->updated_at?->toIso8601String(),
            'snapshot'  => $snap?->payload,
        ]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows'                       => ['required', 'array'],
            'rows.hph'                   => ['array',  'max:10000'],
            'rows.icd'                   => ['array',  'max:10000'],
            'deleted_ids'                => ['nullable', 'array', 'max:10000'],
            'deleted_ids.*'              => ['integer'],
            'snapshot'                   => ['nullable', 'array'],
            'snapshot.formatting'        => ['nullable', 'array'],
            'snapshot.formatting.hph'    => ['nullable', 'array'],
            'snapshot.formatting.icd'    => ['nullable', 'array'],
            'snapshot.formatting_scope'  => ['nullable', 'array'],
            'snapshot.columnlen'         => ['nullable', 'array'],
            'snapshot.columnlen.hph'     => ['nullable', 'array'],
            'snapshot.columnlen.icd'     => ['nullable', 'array'],
            'client_version'             => ['nullable', 'integer'],
        ], [
            'rows.hph.max' => 'Quá nhiều dòng HẠ HPH (tối đa 10000).',
            'rows.icd.max' => 'Quá nhiều dòng HẠ ICD (tối đa 10000).',
        ]);

        try {
            $result = $this->trucking->bulkSave(
                rowsBySheet:   $payload['rows'],
                snapshot:      $payload['snapshot']       ?? null,
                clientVersion: $payload['client_version'] ?? 0,
                editor:        $request->user(),
                deletedIds:    $payload['deleted_ids']    ?? [],
            );
        } catch (DomainException $e) {
            return response()->json(['ok' => false, 'message' => $e->getMessage()] + $e->context(), $e->httpStatus());
        }

        return response()->json(['ok' => true, ...$result]);
    }

    public function resetSnapshot(): JsonResponse
    {
        $this->trucking->resetSnapshot();
        return response()->json(['ok' => true]);
    }
}
