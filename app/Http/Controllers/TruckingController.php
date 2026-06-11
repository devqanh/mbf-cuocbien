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
        $user = $request->user();
        $cols = config('trucking_columns', []);

        // Map quyền cột { key => 'hidden'|'view'|'edit' } cho union 2 sheet
        $perms = [];
        foreach ($this->trucking->allColumns() as $col) {
            $perms[$col['key']] = $user->truckingColumnPermission($col['key']);
        }

        // Danh sách NCC cho dropdown (loại tên có dấu phẩy — Luckysheet dùng ',' separator)
        $suppliers = $this->payable->availableSuppliers()
            ->filter(fn ($s) => ! str_contains($s, ','))
            ->values()
            ->all();

        return view('trucking.index', [
            'colsHph'     => $cols['hph'] ?? [],
            'colsIcd'     => $cols['icd'] ?? [],
            'columnPerms' => $perms,
            'userPrefs'   => $user->trucking_column_prefs ?? [],
            'suppliers'   => $suppliers,
            'canDelete'   => $user->can('shipments.delete'),
        ]);
    }

    /** API: dữ liệu 2 sheet + formatting snapshot (đã lọc theo quyền cột). */
    public function data(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = $this->trucking->listForGrid($user);
        $snap = $this->snapshots->get($this->trucking->sheetKey());
        $payload = $this->trucking->filterSnapshotForUser($snap?->payload, $user);

        return response()->json([
            'data'      => ['hph' => $rows['hph'], 'icd' => $rows['icd']],
            'version'   => $snap?->version ?? 0,
            'editor'    => $snap?->editor?->only(['id', 'name']),
            'updatedAt' => $snap?->updated_at?->toIso8601String(),
            'snapshot'  => $payload,
        ]);
    }

    /** Lưu cột user tự chọn ẩn (preference cá nhân). */
    public function updateColumnPrefs(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hidden'   => ['array'],
            'hidden.*' => ['string'],
        ]);

        $validKeys = array_column($this->trucking->allColumns(), 'key');
        $hidden = array_values(array_intersect($data['hidden'] ?? [], $validKeys));

        $user = $request->user();
        $user->trucking_column_prefs = $hidden ?: null;
        $user->save();

        return response()->json(['ok' => true, 'hidden' => $hidden]);
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

    /** /tailieu — tài liệu cột & công thức (render Markdown → HTML) cho kế toán xem. */
    public function docs()
    {
        $md    = $this->trucking->buildMarkdownDoc();
        $html  = \Illuminate\Support\Str::markdown($md, ['html_input' => 'allow']);
        $notes = file_exists(storage_path('app/trucking_notes.md'))
            ? file_get_contents(storage_path('app/trucking_notes.md'))
            : '';

        return view('trucking.docs', ['html' => $html, 'notes' => $notes]);
    }

    /** Lưu góp ý kế toán vào file. */
    public function saveNotes(Request $request): \Illuminate\Http\JsonResponse
    {
        $text = $request->input('notes', '');
        file_put_contents(storage_path('app/trucking_notes.md'), $text);

        return response()->json(['ok' => true]);
    }

    /** Tải file .md để gửi kế toán. */
    public function docsDownload(): \Symfony\Component\HttpFoundation\Response
    {
        $md = $this->trucking->buildMarkdownDoc();

        return response($md, 200, [
            'Content-Type'        => 'text/markdown; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="TRUCKING_COLUMNS.md"',
        ]);
    }
}
