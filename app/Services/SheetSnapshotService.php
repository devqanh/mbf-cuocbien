<?php

namespace App\Services;

use App\Exceptions\Domain\SnapshotConflictException;
use App\Models\SheetSnapshot;

/**
 * Quản lý snapshot bảng tính Luckysheet với optimistic locking.
 * Tái sử dụng được cho mọi sheet khác trong tương lai (chỉ cần đổi `key`).
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
     * @throws SnapshotConflictException nếu client_version != server_version hiện tại
     */
    public function save(string $key, array $payload, int $clientVersion, ?int $userId = null): SheetSnapshot
    {
        $this->assertVersionMatches($key, $clientVersion);

        $serverVersion = $this->currentVersion($key);

        return SheetSnapshot::updateOrCreate(
            ['key' => $key],
            [
                'payload'    => $payload,
                'version'    => $serverVersion + 1,
                'updated_by' => $userId,
            ]
        );
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

    /** Xoá snapshot — quay về build từ DB. */
    public function reset(string $key): void
    {
        SheetSnapshot::where('key', $key)->delete();
    }
}
