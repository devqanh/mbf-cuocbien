<?php

namespace App\Services;

use App\Events\SheetUpdated;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Shipment;
use App\Models\User;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    /** Prefix snapshot key, dán thêm period: shipments_grid_2026-05 */
    public const SHEET_KEY_PREFIX = 'shipments_grid_';

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
        $fromRows = Shipment::query()
            ->select('period')
            ->distinct()
            ->pluck('period');

        $fromSnapshots = DB::table('sheet_snapshots')
            ->where('key', 'like', self::SHEET_KEY_PREFIX . '%')
            ->pluck('key')
            ->map(fn ($k) => str_replace(self::SHEET_KEY_PREFIX, '', $k));

        $current = $this->currentPeriod();

        return $fromRows
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
        return $period;
    }

    /** Xoá tháng (cả data lẫn snapshot). Cẩn thận! */
    public function deletePeriod(string $period): void
    {
        DB::transaction(function () use ($period) {
            Shipment::inPeriod($period)->delete();
            $this->snapshots->reset($this->sheetKey($period));
        });
    }

    /**
     * Lấy shipments của 1 tháng, group theo direction.
     * Nếu có $user → filter cột hidden trên từng row.
     *
     * @return array{import: Collection, export: Collection}
     */
    public function listForGrid(string $period, ?User $user = null): array
    {
        $rows = Shipment::inPeriod($period)->orderBy('id')->get();

        $hidden = $user ? $this->hiddenColumnKeys($user) : [];

        $mapRow = function (Shipment $s) use ($hidden) {
            $row = $this->toGridRow($s);
            foreach ($hidden as $k) {
                unset($row[$k]);
            }
            return $row;
        };

        return [
            'import' => $rows->where('direction', Shipment::DIRECTION_IMPORT)->values()->map($mapRow),
            'export' => $rows->where('direction', Shipment::DIRECTION_EXPORT)->values()->map($mapRow),
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
     * @param array{import: array, export: array} $rowsByDirection
     * @return array{saved:int, ids:array, version:int}
     */
    public function bulkSave(
        string $period,
        array $rowsByDirection,
        ?array $snapshot,
        int $clientVersion,
        User $editor,
    ): array {
        $key = $this->sheetKey($period);

        // Chỉ check optimistic lock khi user thực sự update snapshot.
        // User restricted không lưu snapshot → bỏ qua version check, save row tự do.
        $canUpdateSnapshot = $editor->isSuperAdmin()
            || empty(array_filter($editor->column_permissions ?? [], fn ($v) => in_array($v, ['hidden', 'view'])));
        if ($snapshot && $canUpdateSnapshot) {
            $this->snapshots->assertVersionMatches($key, $clientVersion);
        }

        // Strip những key user không có quyền edit (vd chỉ xem) — bảo vệ backend
        $editableKeys = $this->editableColumnKeysFor($editor);

        $ids = DB::transaction(function () use ($rowsByDirection, $period, $editableKeys, $editor) {
            $saved = [];
            $seenIds = [];                       // Dedup ID: lần đầu = update; lần sau (copy-paste) = create new
            $isSuper = $editor->isSuperAdmin();
            foreach ([Shipment::DIRECTION_IMPORT, Shipment::DIRECTION_EXPORT] as $direction) {
                foreach ($rowsByDirection[$direction] ?? [] as $row) {
                    $row = $this->normalize($row);
                    if (empty($row['client'])) continue;

                    $id = $row['id'] ?? null;
                    unset($row['id']);

                    // Dedup: nếu id đã được dùng trong batch này → treat as new (tránh ghi đè)
                    if ($id && in_array($id, $seenIds, true)) {
                        $id = null;
                    }
                    if ($id) $seenIds[] = $id;

                    if (! $isSuper) {
                        $row = array_intersect_key($row, array_flip($editableKeys));
                    }

                    $row['period']    = $period;
                    $row['direction'] = $direction;

                    $shipment = $id ? Shipment::find($id) : null;
                    $shipment = $shipment
                        ? tap($shipment)->update($row)
                        : Shipment::create($row);

                    $saved[] = $shipment->id;
                }
            }
            return $saved;
        });

        $newVersion = $this->snapshots->currentVersion($key);

        // Save snapshot — chỉ khi user FULL quyền (đã tính $canUpdateSnapshot ở trên)
        if ($snapshot && $canUpdateSnapshot) {
            $newVersion = $this->snapshots->save($key, $snapshot, $clientVersion, $editor->id)->version;
        }

        broadcast(new SheetUpdated(
            sheetKey:   $key,
            version:    $newVersion,
            editorId:   $editor->id,
            editorName: $editor->name,
            savedRows:  count($ids),
        ))->toOthers();

        return ['saved' => count($ids), 'ids' => $ids, 'version' => $newVersion];
    }

    public function resetSnapshot(string $period): void
    {
        $this->snapshots->reset($this->sheetKey($period));
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
