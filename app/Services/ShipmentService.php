<?php

namespace App\Services;

use App\Events\SheetUpdated;
use App\Exceptions\Domain\BusinessRuleException;
use App\Exceptions\Domain\SnapshotConflictException;
use App\Models\Shipment;
use App\Models\User;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ShipmentService
{
    /** Prefix snapshot key, dán thêm period: shipments_grid_2026-05 */
    public const SHEET_KEY_PREFIX = 'shipments_grid_';

    /** Memoize per-request — listPeriods() được gọi nhiều lần/page render */
    private ?array $cachedPeriods = null;

    public function __construct(
        private readonly SheetSnapshotService $snapshots,
    ) {}

    public function sheetKey(string $period): string
    {
        return self::SHEET_KEY_PREFIX . $period;
    }

    /** Period hiện tại (YYYY-MM). */
    public function currentPeriod(): string
    {
        return now()->format('Y-m');
    }

    /**
     * Danh sách tháng có trong hệ thống (đã từng có dữ liệu hoặc snapshot).
     * Sắp xếp GIẢM DẦN — tháng mới nhất ở đầu mảng (hiển thị bên TRÁI trong UI tab).
     *
     * @return array<int,string>  vd: ['2026-06','2026-05','2025-12']
     */
    public function listPeriods(): array
    {
        if ($this->cachedPeriods !== null) return $this->cachedPeriods;

        $fromRows = Shipment::query()
            ->select('period')
            ->distinct()
            ->pluck('period');

        $fromSnapshots = DB::table('sheet_snapshots')
            ->where('key', 'like', self::SHEET_KEY_PREFIX . '%')
            ->pluck('key')
            ->map(fn ($k) => str_replace(self::SHEET_KEY_PREFIX, '', $k));

        $current = $this->currentPeriod();

        return $this->cachedPeriods = $fromRows
            ->merge($fromSnapshots)
            ->push($current)                      // luôn đảm bảo tháng hiện tại có mặt
            ->unique()
            ->sortDesc()                          // mới nhất → cũ nhất
            ->values()
            ->all();
    }

    /** Tháng mới nhất trong hệ thống (max trong DB hoặc current nếu trống). */
    public function latestPeriod(): string
    {
        $periods = $this->listPeriods();
        return $periods[0] ?? $this->currentPeriod();
    }

    /**
     * Tạo tháng mới (đặt bookmark). Thực ra chỉ là tạo 1 snapshot rỗng — để period hiện trong tab list.
     */
    public function createPeriod(string $period, ?int $userId = null): string
    {
        if (! preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period)) {
            throw new BusinessRuleException('Định dạng tháng không hợp lệ. Yêu cầu YYYY-MM (vd: 2026-06).');
        }
        if (in_array($period, $this->listPeriods(), true)) {
            throw new BusinessRuleException("Tháng $period đã tồn tại.");
        }

        // Tạo snapshot rỗng để đánh dấu period đã tồn tại — bypass optimistic check
        $this->snapshots->save($this->sheetKey($period), [], 0, $userId);
        $this->cachedPeriods = null;   // invalidate per-request cache
        return $period;
    }

    /** Xoá tháng (cả data lẫn snapshot). Cẩn thận! */
    public function deletePeriod(string $period): void
    {
        DB::transaction(function () use ($period) {
            Shipment::inPeriod($period)->delete();
            $this->snapshots->reset($this->sheetKey($period));
        });
        $this->cachedPeriods = null;
    }

    /**
     * Lấy shipments của 1 tháng, group theo direction.
     * Nếu có $user → filter cột hidden trên từng row.
     *
     * @return array{import: Collection, export: Collection}
     */
    public function listForGrid(string $period, ?User $user = null): array
    {
        // 1 query + 1 lần traverse groupBy thay vì where() 2 lần qua collection
        // (where Collection scan toàn bộ rows mỗi lần gọi → O(N) × 2 lần)
        $rowsByDir = Shipment::inPeriod($period)
            ->orderBy('id')
            ->get()
            ->groupBy('direction');

        $hidden = $user ? $this->hiddenColumnKeys($user) : [];

        $mapRow = function (Shipment $s) use ($hidden) {
            $row = $this->toGridRow($s);
            foreach ($hidden as $k) {
                unset($row[$k]);
            }
            return $row;
        };

        return [
            'import' => ($rowsByDir[Shipment::DIRECTION_IMPORT] ?? collect())->values()->map($mapRow),
            'export' => ($rowsByDir[Shipment::DIRECTION_EXPORT] ?? collect())->values()->map($mapRow),
        ];
    }

    /**
     * Lọc snapshot Luckysheet trước khi gửi cho user — bỏ cell nằm trong cột hidden.
     * Cần biết thứ tự cột (column index) để filter celldata.
     */
    public function filterSnapshotForUser(?array $snapshot, User $user): ?array
    {
        if (! $snapshot) return $snapshot;

        $cols = config('shipment_columns', []);
        $hiddenIdx = [];
        foreach ($cols as $i => $col) {
            if ($user->columnPermission($col['key']) === User::PERM_HIDDEN) {
                $hiddenIdx[] = $i;
            }
        }
        if (empty($hiddenIdx)) return $snapshot;

        foreach ($snapshot as &$sheet) {
            if (! isset($sheet['celldata']) || ! is_array($sheet['celldata'])) continue;
            $sheet['celldata'] = array_values(array_filter(
                $sheet['celldata'],
                fn ($cell) => ! in_array($cell['c'] ?? -1, $hiddenIdx, true)
            ));
        }
        return $snapshot;
    }

    /** Danh sách key các cột mà user KHÔNG được xem. */
    public function hiddenColumnKeys(User $user): array
    {
        if ($user->isSuperAdmin()) return [];

        $perms = $user->column_permissions ?? [];
        return array_keys(array_filter($perms, fn ($v) => $v === User::PERM_HIDDEN));
    }

    /** Danh sách key các cột mà user được PHÉP edit. */
    public function editableColumnKeysFor(User $user): array
    {
        $cols = config('shipment_columns', []);
        // Luôn cho phép period/direction (internal field cần thiết)
        $always = ['client'];   // client bắt buộc để identify dòng

        if ($user->isSuperAdmin()) {
            return array_merge($always, array_column($cols, 'key'));
        }

        $editable = [];
        foreach ($cols as $col) {
            if (! empty($col['readonly'])) continue;       // cột metadata (id) không edit
            if ($user->canEditColumn($col['key'])) {
                $editable[] = $col['key'];
            }
        }
        return array_unique(array_merge($always, $editable));
    }

    public function toGridRow(Shipment $s): array
    {
        $row = $s->only($s->getFillable());
        $row['id'] = $s->id;

        // Format dates → Y-m-d (frontend Luckysheet parse được)
        foreach (Shipment::DATE_FIELDS as $f) {
            $row[$f] = $s->{$f}?->format('Y-m-d');
        }

        // Decimal → number (loại bỏ trailing zeros để hiển thị gọn)
        foreach (Shipment::DECIMAL_FIELDS as $f) {
            $row[$f] = $s->{$f} !== null ? (float) $s->{$f} : null;
        }

        // cell_formulas — đã cast ARRAY, đảm bảo object (không phải null) cho frontend
        $row['cell_formulas'] = is_array($s->cell_formulas) ? $s->cell_formulas : null;

        return $row;
    }

    public function create(string $period, string $direction, array $data): Shipment
    {
        return Shipment::create($this->normalize($data) + [
            'period' => $period, 'direction' => $direction,
        ]);
    }

    public function update(Shipment $shipment, array $data): Shipment
    {
        $shipment->update($this->normalize($data));
        return $shipment;
    }

    public function delete(Shipment $shipment): void
    {
        $shipment->delete();
    }

    /**
     * Bulk save 1 tháng. Nhận rows theo direction + snapshot toàn workbook (2 sheets).
     *
     * Dirty-cell tracking (Mức 2):
     * - Frontend chỉ gửi cell DIRTY (đã sửa từ lúc load), không gửi full row.
     * - Row có `id`: UPDATE chỉ các cột được gửi → không đè cell của user khác đang sửa cột khác.
     * - Row không `id`: CREATE với toàn bộ field được gửi.
     * - Snapshot conflict KHÔNG còn reject row save — chỉ skip lưu snapshot, return cờ
     *   `snapshot_conflict` để frontend xử lý (reload formatting).
     *
     * @param array{import: array, export: array} $rowsByDirection
     * @return array{saved:int, ids:array, version:int, snapshot_conflict:bool}
     */
    public function bulkSave(
        string $period,
        array $rowsByDirection,
        ?array $snapshot,
        int $clientVersion,
        User $editor,
        array $deletedIds = [],
    ): array {
        $key = $this->sheetKey($period);

        $canUpdateSnapshot = $editor->isSuperAdmin()
            || empty(array_filter($editor->column_permissions ?? [], fn ($v) => in_array($v, ['hidden', 'view'])));

        $editableKeys = $this->editableColumnKeysFor($editor);

        // DELETE rows user đã xóa khỏi sheet (require shipments.delete permission).
        // Filter theo period để tránh xóa nhầm row của period khác (defensive).
        $deletedCount = 0;
        if (! empty($deletedIds) && $editor->can('shipments.delete')) {
            $deletedCount = Shipment::inPeriod($period)
                ->whereIn('id', array_unique(array_map('intval', $deletedIds)))
                ->delete();
        }

        $ids = DB::transaction(function () use ($rowsByDirection, $period, $editableKeys, $editor) {
            $isSuper = $editor->isSuperAdmin();
            $now     = now()->format('Y-m-d H:i:s');

            // 1) Pre-check tồn tại ID — 1 query duy nhất thay vì N find()
            $incomingIds = [];
            foreach ([Shipment::DIRECTION_IMPORT, Shipment::DIRECTION_EXPORT] as $dir) {
                foreach ($rowsByDirection[$dir] ?? [] as $row) {
                    if (! empty($row['id'])) $incomingIds[] = (int) $row['id'];
                }
            }
            $existingIds = $incomingIds
                ? Shipment::whereIn('id', array_unique($incomingIds))->pluck('id')->flip()->all()
                : [];

            $saved           = [];
            $seenIds         = [];
            $creates         = [];
            $createPositions = [];

            foreach ([Shipment::DIRECTION_IMPORT, Shipment::DIRECTION_EXPORT] as $direction) {
                foreach ($rowsByDirection[$direction] ?? [] as $row) {
                    $row = $this->normalize($row);

                    $id = $row['id'] ?? null;
                    unset($row['id']);

                    // Dedup: id đã dùng trong batch → treat as new (copy-paste)
                    if ($id && in_array($id, $seenIds, true))     $id = null;
                    // Stale: id không còn tồn tại trong DB (xoá concurrent) → treat as new
                    if ($id && ! isset($existingIds[$id]))         $id = null;

                    // NEW row bắt buộc có client (đảm bảo có identity); UPDATE thì không cần
                    if (! $id && empty($row['client'])) continue;

                    if ($id) $seenIds[] = $id;

                    if (! $isSuper) {
                        // cell_formulas là metadata cấp row, không phải column — luôn cho qua
                        $row = array_intersect_key($row, array_flip([...$editableKeys, 'cell_formulas']));
                    }

                    if ($id) {
                        // PARTIAL UPDATE — chỉ update các cell được gửi (dirty).
                        // KHÔNG inject period/direction → user khác đang edit cột khác không bị đè.
                        if (empty($row)) {
                            $saved[] = $id;   // không có gì để update nhưng vẫn report id
                            continue;
                        }
                        $row['updated_at'] = $now;
                        Shipment::where('id', $id)->update($row);
                        $saved[] = $id;
                    } else {
                        // CREATE — inject period/direction
                        $row['period']     = $period;
                        $row['direction']  = $direction;
                        $row['created_at'] = $now;
                        $row['updated_at'] = $now;
                        $createPositions[] = count($saved);
                        $saved[]           = null;
                        $creates[]         = $row;
                    }
                }
            }

            if (! empty($creates)) {
                // Batch insert có thể cần keys đồng nhất (DB::insert yêu cầu mọi row cùng schema).
                // Đảm bảo mọi create row có cùng key set bằng cách padding null.
                $allKeys = [];
                foreach ($creates as $row) {
                    foreach ($row as $k => $_) $allKeys[$k] = true;
                }
                $keyList = array_keys($allKeys);
                $padded = array_map(function ($row) use ($keyList) {
                    $out = [];
                    foreach ($keyList as $k) $out[$k] = $row[$k] ?? null;
                    return $out;
                }, $creates);

                DB::table('shipments')->insert($padded);
                $startId = (int) DB::getPdo()->lastInsertId();
                foreach ($createPositions as $i => $pos) {
                    $saved[$pos] = $startId + $i;
                }
            }

            return $saved;
        });

        // 2) Snapshot conflict → KHÔNG reject row save, chỉ skip lưu snapshot
        $snapshotConflict = false;
        $newVersion = $this->snapshots->currentVersion($key);

        if ($snapshot && $canUpdateSnapshot) {
            // Merge formatting overlay với existing — preserve format của cols user
            // không thấy (user-hidden cá nhân) để không mất bg của other users.
            if (isset($snapshot['formatting']) && is_array($snapshot['formatting'])) {
                $snapshot = $this->mergeFormattingWithExisting($key, $snapshot);
            }

            try {
                $newVersion = $this->snapshots->save($key, $snapshot, $clientVersion, $editor->id)->version;
            } catch (SnapshotConflictException $e) {
                $snapshotConflict = true;
                $newVersion = $this->snapshots->currentVersion($key);
            }
        }

        // Broadcast best-effort — nếu Reverb/queue down KHÔNG được làm fail save.
        // (QUEUE_CONNECTION=database: dispatch chỉ insert vào jobs table → ít khi fail.
        //  QUEUE_CONNECTION=sync: chạy ngay → nếu Reverb down sẽ throw. Catch để an toàn.)
        try {
            broadcast(new SheetUpdated(
                sheetKey:   $key,
                version:    $newVersion,
                editorId:   $editor->id,
                editorName: $editor->name,
                savedRows:  count($ids),
            ))->toOthers();
        } catch (Throwable $e) {
            Log::channel('single')->warning('Broadcast SheetUpdated failed (Reverb/queue?)', [
                'sheetKey' => $key,
                'error'    => $e->getMessage(),
            ]);
        }

        return [
            'saved'             => count($ids),
            'deleted'           => $deletedCount,
            'ids'               => $ids,
            'version'           => $newVersion,
            'snapshot_conflict' => $snapshotConflict,
        ];
    }

    public function resetSnapshot(string $period): void
    {
        $this->snapshots->reset($this->sheetKey($period));
    }

    /**
     * Merge formatting overlay từ frontend với existing trên server.
     *
     * User chỉ thấy SUBSET cols (do USER_HIDDEN cá nhân). Frontend gửi:
     * - formatting: entries cho cols user thấy
     * - formatting_scope: list col keys user đang thấy
     *
     * Logic merge: cho mỗi direction, giữ entries của cols KHÔNG nằm trong scope
     * (user không thấy, không nên ghi đè), nhận entries từ frontend cho cols
     * trong scope (user có quyền edit).
     */
    private function mergeFormattingWithExisting(string $key, array $snapshot): array
    {
        $scope = $snapshot['formatting_scope'] ?? null;
        unset($snapshot['formatting_scope']);

        // No scope = no merge needed (legacy clients hoặc super-admin full scope)
        if (! is_array($scope) || empty($scope)) {
            return $snapshot;
        }

        $existing = $this->snapshots->get($key);
        $existingFmt = null;
        $existingColumnlen = null;
        if ($existing && is_array($existing->payload ?? null)) {
            if (isset($existing->payload['formatting']) && is_array($existing->payload['formatting'])) {
                $existingFmt = $existing->payload['formatting'];
            }
            if (isset($existing->payload['columnlen']) && is_array($existing->payload['columnlen'])) {
                $existingColumnlen = $existing->payload['columnlen'];
            }
        }

        $scopeSet = array_flip($scope);

        // Merge columnlen — keep widths cho cols OUTSIDE scope, accept new cho IN scope
        if (isset($snapshot['columnlen']) && is_array($snapshot['columnlen']) && is_array($existingColumnlen)) {
            $mergedCl = ['import' => [], 'export' => []];
            foreach (['import', 'export'] as $dir) {
                $merged = [];
                // Keep existing widths for hidden cols
                foreach (($existingColumnlen[$dir] ?? []) as $colKey => $width) {
                    if (! isset($scopeSet[$colKey])) $merged[$colKey] = $width;
                }
                // Accept user's new widths for visible cols
                foreach (($snapshot['columnlen'][$dir] ?? []) as $colKey => $width) {
                    if (isset($scopeSet[$colKey])) $merged[$colKey] = $width;
                }
                $mergedCl[$dir] = $merged;
            }
            $snapshot['columnlen'] = $mergedCl;
        }

        if (! is_array($existingFmt)) {
            // No existing formatting → store as-is (formatting + columnlen)
            return $snapshot;
        }

        $scopeSet = array_flip($scope);
        $newFmt   = $snapshot['formatting'];
        $merged   = ['import' => [], 'export' => []];

        foreach (['import', 'export'] as $dir) {
            $byKey = [];

            // Keep existing entries for cols OUTSIDE user's scope
            foreach (($existingFmt[$dir] ?? []) as $entry) {
                if (! isset($scopeSet[$entry['col'] ?? ''])) {
                    $byKey[($entry['id'] ?? '') . ':' . ($entry['col'] ?? '')] = $entry;
                }
            }

            // Apply user's new entries (only for cols in scope)
            foreach (($newFmt[$dir] ?? []) as $entry) {
                if (! isset($scopeSet[$entry['col'] ?? ''])) continue;
                $byKey[($entry['id'] ?? '') . ':' . ($entry['col'] ?? '')] = $entry;
            }

            $merged[$dir] = array_values($byKey);
        }

        $snapshot['formatting'] = $merged;
        return $snapshot;
    }

    /** Tất cả cột text (chuyển '' → null). */
    private const TEXT_FIELDS = [
        'client', 'hbl', 'mbl_no', 'bkg_no', 'pol', 'pod', 'vol', 'container_type',
        'vessel_name', 'line', 'note',
        // status text
        'vgm', 'si', 'bl_draft', 'bl_confirm', 'obl', 'tlx', 'swb', 'shipment_done',
        // mua / NCC
        'purchase_note', 'supplier', 'purchase_invoice_no',
        // agent
        'driver_hoa', 'agent_name',
        // bán / KH
        'sale_note', 'customer', 'sale_invoice_no',
    ];

    /** Chuẩn hoá row (text → null nếu rỗng, parse date, parse decimal). */
    private function normalize(array $row): array
    {
        $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);

        foreach (self::TEXT_FIELDS as $f) {
            if (isset($row[$f]) && $row[$f] === '') $row[$f] = null;
        }
        foreach (Shipment::DATE_FIELDS as $f) {
            if (array_key_exists($f, $row)) $row[$f] = $this->parseDate($row[$f]);
        }
        foreach (Shipment::DECIMAL_FIELDS as $f) {
            if (array_key_exists($f, $row)) $row[$f] = $this->parseDecimal($row[$f]);
        }

        // cell_formulas: array { colKey: "=SUM(...)" } → JSON encode để dùng được với
        // Shipment::where->update() và DB::table->insert() (bypass Eloquent cast).
        // Empty map → null để xoá formula đã lưu.
        if (array_key_exists('cell_formulas', $row)) {
            $v = $row['cell_formulas'];
            if (! is_array($v) || empty(array_filter($v, fn ($x) => $x !== null && $x !== ''))) {
                $row['cell_formulas'] = null;
            } else {
                // Drop entries empty/null, chỉ giữ string formula bắt đầu bằng '='
                $clean = [];
                foreach ($v as $k => $val) {
                    if (is_string($val) && $val !== '') {
                        $clean[$k] = $val;
                    }
                }
                $row['cell_formulas'] = empty($clean) ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
            }
        }
        return $row;
    }

    /** Parse "1,000,000 VNĐ" / "1000000" / "1.5e6" → 1000000.0 (hoặc null). */
    private function parseDecimal($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;

        $s = preg_replace('/[^0-9,.\-]/', '', (string) $v);  // chỉ giữ số, dấu phẩy, chấm, âm
        $s = str_replace(',', '', $s);                       // bỏ separator hàng nghìn
        return is_numeric($s) ? (float) $s : null;
    }

    private function parseDate($value): ?string
    {
        if (empty($value)) return null;
        if ($value instanceof DateTimeInterface) return $value->format('Y-m-d');

        $value = trim((string) $value);
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'm/d/Y'] as $f) {
            $d = DateTime::createFromFormat($f, $value);
            if ($d && $d->format($f) === $value) return $d->format('Y-m-d');
        }
        if (is_numeric($value) && $value > 25569 && $value < 60000) {
            return date('Y-m-d', ((int) $value - 25569) * 86400);
        }
        return null;
    }
}
