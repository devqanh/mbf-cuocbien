<?php

namespace App\Services;

use App\Events\SheetUpdated;
use App\Exceptions\Domain\SnapshotConflictException;
use App\Models\TruckingEntry;
use App\Models\User;
use DateTime;
use DateTimeInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Service cho tính năng Trucking — phỏng theo ShipmentService nhưng:
 *  - KHÔNG có period (1 bảng liên tục).
 *  - Phân biệt sheet 'hph' | 'icd' (thay direction import/export).
 *  - Mỗi sheet có bộ cột riêng → frontend tự render; backend chỉ lưu union.
 */
class TruckingService
{
    /** Snapshot key cố định (không có period). */
    public const SHEET_KEY = 'trucking_grid';

    public function __construct(
        private readonly SheetSnapshotService $snapshots,
    ) {}

    public function sheetKey(): string
    {
        return self::SHEET_KEY;
    }

    /** Union các cột 2 sheet (dedupe theo key) — dùng cho phân quyền. */
    public function allColumns(): array
    {
        $cols = config('trucking_columns', []);
        $byKey = [];
        foreach (['hph', 'icd'] as $sheet) {
            foreach ($cols[$sheet] ?? [] as $c) {
                if (! isset($byKey[$c['key']])) $byKey[$c['key']] = $c;
            }
        }
        return array_values($byKey);
    }

    /** Diễn giải công thức bằng TÊN CỘT tiếng Việt (cho tài liệu). */
    public function formulaLabel(array $spec, array $titleByKey): string
    {
        $t = fn ($key) => $titleByKey[$key] ?? $key;
        return match ($spec['op'] ?? '') {
            'sum'  => implode(' + ', array_map($t, $spec['cols'])),
            'sub'  => implode(' − ', array_map($t, $spec['cols'])),
            'mul'  => $t($spec['col']) . ' × ' . (($spec['factor'] ?? 0) * 100) . '%',
            'expr' => str_replace(['*', '+', '-'], [' × ', ' + ', ' − '],
                        preg_replace_callback('/\{(\w+)\}/', fn ($m) => $t($m[1]), $spec['template'])),
            default => '',
        };
    }

    /**
     * Sinh tài liệu Markdown mô tả toàn bộ cột + công thức 2 sheet (cho kế toán).
     * Sinh trực tiếp từ config nên luôn khớp hệ thống.
     */
    public function buildMarkdownDoc(): string
    {
        $cfg = config('trucking_columns', []);
        $groupNames = [
            1 => 'Thông tin lô hàng', 2 => 'Chi phí', 3 => 'Chi phí xe ngoài / Thanh toán',
            4 => 'Chi phí xe MBF chạy', 5 => 'Doanh thu',
        ];
        $typeLabel = ['text' => 'Chữ', 'date' => 'Ngày', 'vnd' => 'Tiền (VNĐ)', 'number' => 'Số'];
        $sheetNames = ['hph' => 'HẠ HPH (Hải Phòng)', 'icd' => 'HẠ ICD (Quế Võ)'];

        // map key→title (toàn cục, để diễn giải công thức)
        $titleByKey = [];
        foreach ($cfg as $list) foreach ($list as $c) $titleByKey[$c['key']] = $c['title'];

        $md = "# Tài liệu cột & công thức — Bảng TRUCKING\n\n";
        $md .= "> Tài liệu này sinh tự động từ cấu hình hệ thống. Hệ thống gồm **2 sheet**: HẠ HPH và HẠ ICD.\n";
        $md .= "> Các ô **TỔNG / VAT / CÒN NỢ / PHẢI THU** tự tính theo công thức (cập nhật ngay khi nhập). Cột tô màu xám là **ô tính tự động**, không nhập tay.\n\n";

        foreach (['hph', 'icd'] as $sheet) {
            $cols = $cfg[$sheet] ?? [];
            $md .= "---\n\n## SHEET: " . ($sheetNames[$sheet] ?? $sheet) . "\n\n";
            $md .= "Tổng số cột: **" . count($cols) . "**\n\n";

            $byGroup = [];
            foreach ($cols as $c) $byGroup[$c['group'] ?? 0][] = $c;
            ksort($byGroup);

            foreach ($byGroup as $gid => $gcols) {
                $md .= "### Nhóm {$gid} — " . ($groupNames[$gid] ?? "Nhóm {$gid}") . "\n\n";
                $md .= "| Cột | Kiểu | Công thức / Ghi chú |\n|---|---|---|\n";
                foreach ($gcols as $c) {
                    $type = $typeLabel[$c['type'] ?? 'text'] ?? 'Chữ';
                    if (! empty($c['readonly'])) {
                        $note = '_Tự động (số thứ tự dòng)_';
                    } elseif (! empty($c['formula'])) {
                        $note = '**= ' . $this->formulaLabel($c['formula'], $titleByKey) . '**';
                    } else {
                        $note = 'Nhập tay';
                    }
                    $md .= '| ' . $c['title'] . ' | ' . $type . ' | ' . $note . " |\n";
                }
                $md .= "\n";
            }
        }

        $md .= "---\n\n## Ghi chú nghiệp vụ\n\n";
        $md .= "- **BÊN TT** = bên thanh toán (ghi chữ: tài xế / A.Hoàn / xe ngoài / lập từ TK công ty…), **không** cộng vào tổng.\n";
        $md .= "- **SỐ TIỀN** = các ô nhập số tiền, được cộng vào các ô tổng tương ứng.\n";
        $md .= "- Tiền nhập kiểu Việt Nam đều được: gõ `3000000`, `3.000.000` hay `3,000,000` đều ra `3,000,000 VNĐ`.\n";
        $md .= "- Ngày nhập dạng `20/10/2026` (dd/mm/yyyy).\n";
        $md .= "- Click vào ô tổng để xem công thức hiển thị ngay dưới tiêu đề bảng.\n";

        return $md;
    }

    /** Key các cột user KHÔNG được xem (admin set hidden). */
    public function hiddenColumnKeys(User $user): array
    {
        if ($user->isSuperAdmin()) return [];
        $perms = $user->trucking_column_permissions ?? [];
        return array_keys(array_filter($perms, fn ($v) => $v === User::PERM_HIDDEN));
    }

    /** Key các cột user được PHÉP sửa. */
    public function editableColumnKeysFor(User $user): array
    {
        $cols = $this->allColumns();
        $always = ['customer'];   // bắt buộc để identify dòng

        if ($user->isSuperAdmin()) {
            return array_merge($always, array_column($cols, 'key'));
        }
        $editable = [];
        foreach ($cols as $col) {
            if (! empty($col['readonly'])) continue;
            if ($user->canEditTruckingColumn($col['key'])) $editable[] = $col['key'];
        }
        return array_unique(array_merge($always, $editable));
    }

    /**
     * Lấy entries group theo sheet. Nếu có $user → bỏ cột admin-hidden khỏi từng row.
     *
     * @return array{hph: \Illuminate\Support\Collection, icd: \Illuminate\Support\Collection}
     */
    public function listForGrid(?User $user = null): array
    {
        $bySheet = TruckingEntry::query()
            ->orderBy('id')
            ->get()
            ->groupBy('sheet');

        $hidden = $user ? $this->hiddenColumnKeys($user) : [];
        $mapRow = function (TruckingEntry $e) use ($hidden) {
            $row = $this->toGridRow($e);
            foreach ($hidden as $k) unset($row[$k]);
            return $row;
        };

        return [
            'hph' => ($bySheet[TruckingEntry::SHEET_HPH] ?? collect())->values()->map($mapRow),
            'icd' => ($bySheet[TruckingEntry::SHEET_ICD] ?? collect())->values()->map($mapRow),
        ];
    }

    /** Lọc formatting overlay — bỏ entry của cột user không được xem (anchored theo col key). */
    public function filterSnapshotForUser(?array $snapshot, User $user): ?array
    {
        if (! $snapshot || $user->isSuperAdmin()) return $snapshot;
        $hidden = array_flip($this->hiddenColumnKeys($user));
        if (empty($hidden)) return $snapshot;

        if (isset($snapshot['formatting']) && is_array($snapshot['formatting'])) {
            foreach (['hph', 'icd'] as $sheet) {
                if (! isset($snapshot['formatting'][$sheet]) || ! is_array($snapshot['formatting'][$sheet])) continue;
                $snapshot['formatting'][$sheet] = array_values(array_filter(
                    $snapshot['formatting'][$sheet],
                    fn ($entry) => ! isset($hidden[$entry['col'] ?? ''])
                ));
            }
        }
        return $snapshot;
    }

    public function toGridRow(TruckingEntry $e): array
    {
        $row = $e->only($e->getFillable());
        $row['id'] = $e->id;

        foreach (TruckingEntry::dateFields() as $f) {
            $row[$f] = $e->{$f}?->format('Y-m-d');
        }
        foreach (TruckingEntry::decimalFields() as $f) {
            $row[$f] = $e->{$f} !== null ? (float) $e->{$f} : null;
        }

        $row['cell_formulas'] = is_array($e->cell_formulas) ? $e->cell_formulas : null;

        return $row;
    }

    /**
     * Bulk save — nhận rows theo sheet + snapshot formatting overlay.
     * Dirty-cell tracking: row có id → partial update; không id + có customer → create.
     *
     * @param array{hph: array, icd: array} $rowsBySheet
     * @return array{saved:int, deleted:int, ids:array, version:int, snapshot_conflict:bool}
     */
    public function bulkSave(
        array $rowsBySheet,
        ?array $snapshot,
        int $clientVersion,
        User $editor,
        array $deletedIds = [],
    ): array {
        $key = $this->sheetKey();

        $isSuper      = $editor->isSuperAdmin();
        $editableKeys = $this->editableColumnKeysFor($editor);
        // Chỉ cho lưu formatting toàn workbook nếu KHÔNG bị admin hạn chế cột nào
        $canUpdateSnapshot = $isSuper
            || empty(array_filter($editor->trucking_column_permissions ?? [], fn ($v) => in_array($v, ['hidden', 'view'])));

        // DELETE rows user đã xóa (require quyền)
        $deletedCount = 0;
        if (! empty($deletedIds) && $editor->can('shipments.delete')) {
            $deletedCount = TruckingEntry::whereIn('id', array_unique(array_map('intval', $deletedIds)))->delete();
        }

        $ids = DB::transaction(function () use ($rowsBySheet, $isSuper, $editableKeys) {
            $now = now()->format('Y-m-d H:i:s');

            // Pre-check tồn tại ID — 1 query
            $incomingIds = [];
            foreach ([TruckingEntry::SHEET_HPH, TruckingEntry::SHEET_ICD] as $sheet) {
                foreach ($rowsBySheet[$sheet] ?? [] as $row) {
                    if (! empty($row['id'])) $incomingIds[] = (int) $row['id'];
                }
            }
            $existingIds = $incomingIds
                ? TruckingEntry::whereIn('id', array_unique($incomingIds))->pluck('id')->flip()->all()
                : [];

            $saved = $seenIds = $creates = $createPositions = [];

            foreach ([TruckingEntry::SHEET_HPH, TruckingEntry::SHEET_ICD] as $sheet) {
                foreach ($rowsBySheet[$sheet] ?? [] as $row) {
                    $row = $this->normalize($row);

                    $id = $row['id'] ?? null;
                    unset($row['id']);

                    if ($id && in_array($id, $seenIds, true))  $id = null;   // dup trong batch
                    if ($id && ! isset($existingIds[$id]))      $id = null;   // stale id

                    // NEW row bắt buộc có customer (identity); UPDATE thì không cần
                    if (! $id && empty($row['customer'])) continue;

                    if ($id) $seenIds[] = $id;

                    // Non-super: chỉ giữ cột được phép sửa (+ cell_formulas là metadata cấp row)
                    if (! $isSuper) {
                        $row = array_intersect_key($row, array_flip([...$editableKeys, 'cell_formulas']));
                    }

                    if ($id) {
                        if (empty($row)) { $saved[] = $id; continue; }
                        $row['updated_at'] = $now;
                        TruckingEntry::where('id', $id)->update($row);
                        $saved[] = $id;
                    } else {
                        $row['sheet']      = $sheet;
                        $row['created_at'] = $now;
                        $row['updated_at'] = $now;
                        $createPositions[] = count($saved);
                        $saved[]           = null;
                        $creates[]         = $row;
                    }
                }
            }

            if (! empty($creates)) {
                // Padding null để mọi create row cùng key set (yêu cầu của DB::insert)
                $allKeys = [];
                foreach ($creates as $row) foreach ($row as $k => $_) $allKeys[$k] = true;
                $keyList = array_keys($allKeys);
                $padded = array_map(function ($row) use ($keyList) {
                    $out = [];
                    foreach ($keyList as $k) $out[$k] = $row[$k] ?? null;
                    return $out;
                }, $creates);

                DB::table('trucking_entries')->insert($padded);
                $startId = (int) DB::getPdo()->lastInsertId();
                foreach ($createPositions as $i => $pos) {
                    $saved[$pos] = $startId + $i;
                }
            }

            return $saved;
        });

        // Snapshot (formatting overlay + column widths)
        $snapshotConflict = false;
        $newVersion = $this->snapshots->currentVersion($key);

        if ($snapshot && $canUpdateSnapshot) {
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

        // Broadcast best-effort
        try {
            broadcast(new SheetUpdated(
                sheetKey:   $key,
                version:    $newVersion,
                editorId:   $editor->id,
                editorName: $editor->name,
                savedRows:  count($ids),
            ))->toOthers();
        } catch (Throwable $e) {
            Log::channel('single')->warning('Broadcast SheetUpdated (trucking) failed', [
                'sheetKey' => $key, 'error' => $e->getMessage(),
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

    public function resetSnapshot(): void
    {
        $this->snapshots->reset($this->sheetKey());
    }

    /**
     * Merge formatting overlay từ frontend với existing — anchored theo sheet 'hph'/'icd'.
     * (Trucking không có per-column hidden như shipments nên scope merge đơn giản hơn,
     *  nhưng vẫn giữ cấu trúc để không mất format khi nhiều user cùng sửa.)
     */
    private function mergeFormattingWithExisting(string $key, array $snapshot): array
    {
        $scope = $snapshot['formatting_scope'] ?? null;
        unset($snapshot['formatting_scope']);

        if (! is_array($scope) || empty($scope)) return $snapshot;

        $existing = $this->snapshots->get($key);
        $existingFmt = null;
        if ($existing && is_array($existing->payload['formatting'] ?? null)) {
            $existingFmt = $existing->payload['formatting'];
        }
        if (! is_array($existingFmt)) return $snapshot;

        $scopeSet = array_flip($scope);
        $newFmt   = $snapshot['formatting'];
        $merged   = ['hph' => [], 'icd' => []];

        foreach (['hph', 'icd'] as $sheet) {
            $byKey = [];
            foreach (($existingFmt[$sheet] ?? []) as $entry) {
                if (! isset($scopeSet[$entry['col'] ?? ''])) {
                    $byKey[($entry['id'] ?? '') . ':' . ($entry['col'] ?? '')] = $entry;
                }
            }
            foreach (($newFmt[$sheet] ?? []) as $entry) {
                if (! isset($scopeSet[$entry['col'] ?? ''])) continue;
                $byKey[($entry['id'] ?? '') . ':' . ($entry['col'] ?? '')] = $entry;
            }
            $merged[$sheet] = array_values($byKey);
        }

        $snapshot['formatting'] = $merged;
        return $snapshot;
    }

    /** Chuẩn hoá row: text '' → null, parse date, parse decimal, encode cell_formulas. */
    private function normalize(array $row): array
    {
        $row = array_map(fn ($v) => is_string($v) ? trim($v) : $v, $row);

        foreach (TruckingEntry::textFields() as $f) {
            if (isset($row[$f]) && $row[$f] === '') $row[$f] = null;
        }
        foreach (TruckingEntry::dateFields() as $f) {
            if (array_key_exists($f, $row)) $row[$f] = $this->parseDate($row[$f]);
        }
        foreach (TruckingEntry::decimalFields() as $f) {
            if (array_key_exists($f, $row)) $row[$f] = $this->parseDecimal($row[$f]);
        }

        if (array_key_exists('cell_formulas', $row)) {
            $v = $row['cell_formulas'];
            if (! is_array($v) || empty(array_filter($v, fn ($x) => $x !== null && $x !== ''))) {
                $row['cell_formulas'] = null;
            } else {
                $clean = [];
                foreach ($v as $k => $val) {
                    if (is_string($val) && $val !== '') $clean[$k] = $val;
                }
                $row['cell_formulas'] = empty($clean) ? null : json_encode($clean, JSON_UNESCAPED_UNICODE);
            }
        }
        return $row;
    }

    private function parseDecimal($v): ?float
    {
        if ($v === null || $v === '') return null;
        if (is_numeric($v)) return (float) $v;

        // Hỗ trợ cả "3,000,000" (phẩy) lẫn "3.000.000" (chấm kiểu VN).
        $s = preg_replace('/[^0-9.,\-]/', '', (string) $v);  // giữ số . , -
        $s = str_replace(',', '', $s);                       // phẩy = ngăn nghìn → bỏ
        if (substr_count($s, '.') > 1) {
            $s = str_replace('.', '', $s);                   // nhiều chấm = ngăn nghìn → bỏ
        }
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
