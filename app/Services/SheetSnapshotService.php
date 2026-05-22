<?php

namespace App\Services;

use App\Exceptions\Domain\SnapshotConflictException;
use App\Models\SheetSnapshot;
use App\Models\SheetSnapshotHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Quản lý snapshot bảng tính Luckysheet với optimistic locking.
 * Tái sử dụng được cho mọi sheet khác trong tương lai (chỉ cần đổi `key`).
 *
 * Mỗi save bump version + ghi history (audit trail + rollback).
 * Job prune cũ chạy định kỳ — xem App\Console\Commands\PruneSheetSnapshots.
 */
class SheetSnapshotService
{
    /**
     * Lấy snapshot hiện tại của 1 sheet.
     * Trả về null nếu chưa có.
     */
    public function get(string $key): ?SheetSnapshot
    {
        return SheetSnapshot::with('editor:id,name')
            ->where('key', $key)
            ->first();
    }

    /**
     * Tóm tắt thông tin snapshot dùng cho API response.
     */
    public function summary(string $key): array
    {
        $snap = $this->get($key);

        return [
            'snapshot'  => $snap?->payload,
            'version'   => $snap?->version ?? 0,
            'editor'    => $snap?->editor?->only(['id', 'name']),
            'updatedAt' => $snap?->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Lưu snapshot mới, bump version. Kiểm tra optimistic lock trước.
     *
     * 3.6 — consolidate: 1 SELECT với lockForUpdate thay vì 3 SELECT version riêng.
     *
     * @throws SnapshotConflictException nếu client_version != server_version hiện tại
     */
    /** Ngưỡng cảnh báo payload sau gzip (bytes). 2 MB ≈ ~20 MB JSON raw — nên rà soát. */
    private const PAYLOAD_WARN_BYTES = 2 * 1024 * 1024;

    public function save(string $key, array $payload, int $clientVersion, ?int $userId = null): SheetSnapshot
    {
        // 4.3 — Trim payload trước khi lưu: bỏ m (display string) khi nó trivially derive từ v.
        // Tiết kiệm thêm ~15% sau gzip mà không ảnh hưởng hiển thị (luckysheet re-compute m từ v+ct).
        $payload = $this->trimPayload($payload);

        // 4.6 — Telemetry: warn khi payload sau gzip vượt ngưỡng (theo dõi growth)
        $estimatedBytes = strlen(gzdeflate(json_encode($payload), 6));
        if ($estimatedBytes > self::PAYLOAD_WARN_BYTES) {
            Log::channel('single')->warning('LARGE SNAPSHOT', [
                'key'        => $key,
                'bytes_gzip' => $estimatedBytes,
                'sheets'     => count($payload),
                'editor_id'  => $userId,
            ]);
        }

        return DB::transaction(function () use ($key, $payload, $clientVersion, $userId) {
            // 1 query duy nhất: lock + read version (thay vì 2-3 SELECT lẻ)
            $current = SheetSnapshot::where('key', $key)->lockForUpdate()->first();
            $serverVersion = $current?->version ?? 0;

            // Optimistic lock: chỉ check nếu đã có snapshot cũ
            if ($serverVersion > 0 && $clientVersion !== $serverVersion) {
                throw new SnapshotConflictException($serverVersion, $clientVersion);
            }

            $newVersion = $serverVersion + 1;

            $snapshot = SheetSnapshot::updateOrCreate(
                ['key' => $key],
                [
                    'payload'    => $payload,
                    'version'    => $newVersion,
                    'updated_by' => $userId,
                ]
            );

            // Ghi history — mỗi version đều lưu lại để rollback/audit
            // Payload đã được model cast gzip; truyền array thuần vào, model history cũng tự gzip.
            SheetSnapshotHistory::create([
                'snapshot_key' => $key,
                'version'      => $newVersion,
                'payload'      => $payload,
                'editor_id'    => $userId,
                'created_at'   => now(),
            ]);

            return $snapshot;
        });
    }

    /**
     * Kiểm tra version có khớp với server không. Throw nếu lệch.
     * Tách thành method riêng để controller bulk save có thể check sớm
     * (trước khi xử lý rows) — fail fast.
     */
    public function assertVersionMatches(string $key, int $clientVersion): void
    {
        $serverVersion = $this->currentVersion($key);

        // Nếu chưa có snapshot (version=0), bất kỳ client_version nào cũng OK
        if ($serverVersion === 0) return;

        if ($clientVersion !== $serverVersion) {
            throw new SnapshotConflictException($serverVersion, $clientVersion);
        }
    }

    public function currentVersion(string $key): int
    {
        return (int) SheetSnapshot::where('key', $key)->value('version');
    }

    /** Xoá snapshot — quay về build từ DB. KHÔNG xoá history (giữ audit). */
    public function reset(string $key): void
    {
        SheetSnapshot::where('key', $key)->delete();
    }

    /**
     * Rollback snapshot về 1 version cụ thể trong history.
     * Bump current version lên (chứ không quay ngược) để giữ optimistic lock chain.
     */
    public function rollback(string $key, int $toVersion, ?int $userId = null): SheetSnapshot
    {
        $history = SheetSnapshotHistory::where('snapshot_key', $key)
            ->where('version', $toVersion)
            ->firstOrFail();

        return $this->save($key, $history->payload, $this->currentVersion($key), $userId);
    }

    /**
     * Trim payload Luckysheet trước khi lưu — bỏ trường thừa mà không ảnh hưởng hiển thị.
     *
     * Conservative: chỉ drop khi safe (luckysheet re-compute được).
     */
    private function trimPayload(array $payload): array
    {
        foreach ($payload as &$sheet) {
            if (! isset($sheet['celldata']) || ! is_array($sheet['celldata'])) continue;

            foreach ($sheet['celldata'] as &$cell) {
                if (! isset($cell['v']) || ! is_array($cell['v'])) continue;
                $v = &$cell['v'];

                // Drop 'm' nếu trivially derive từ 'v' — luckysheet re-format dựa trên ct.
                if (isset($v['v'], $v['m'])) {
                    $raw = $v['v'];
                    $display = $v['m'];
                    if (is_string($raw) && $raw === $display) {
                        unset($v['m']);
                    } elseif (is_numeric($raw) && (string) $raw === $display) {
                        unset($v['m']);
                    }
                }

                // Drop ct mặc định
                if (isset($v['ct']['fa']) && $v['ct']['fa'] === 'General'
                    && isset($v['ct']['t']) && $v['ct']['t'] === 'g'
                    && count($v['ct']) === 2) {
                    unset($v['ct']);
                }
            }
        }
        return $payload;
    }
}
