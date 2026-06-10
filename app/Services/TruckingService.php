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

    /**
     * Lấy entries group theo sheet.
     *
     * @return array{hph: \Illuminate\Support\Collection, icd: \Illuminate\Support\Collection}
     */
    public function listForGrid(): array
    {
        $bySheet = TruckingEntry::query()
            ->orderBy('id')
            ->get()
            ->groupBy('sheet');

        return [
            'hph' => ($bySheet[TruckingEntry::SHEET_HPH] ?? collect())->values()->map(fn ($e) => $this->toGridRow($e)),
            'icd' => ($bySheet[TruckingEntry::SHEET_ICD] ?? collect())->values()->map(fn ($e) => $this->toGridRow($e)),
        ];
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

        // DELETE rows user đã xóa (require quyền)
        $deletedCount = 0;
        if (! empty($deletedIds) && $editor->can('shipments.delete')) {
            $deletedCount = TruckingEntry::whereIn('id', array_unique(array_map('intval', $deletedIds)))->delete();
        }

        $ids = DB::transaction(function () use ($rowsBySheet) {
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

        if ($snapshot) {
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
