<?php

namespace App\Concerns;

use App\Support\Hashid;

/**
 * Model dùng HASHID làm khóa route: URL hiện chuỗi khó đoán thay cho id số tuần tự.
 * - getRouteKey(): route()/link tự sinh ra hashid.
 * - resolveRouteBinding(): nhận hashid từ URL → decode → find theo id thật.
 * Không thêm cột nào; id số vẫn nguyên trong DB & JSON (chỉ thêm field 'hashid' khi cần).
 */
trait HasHashid
{
    public function getRouteKey()
    {
        return Hashid::encode((int) $this->getKey());
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if ($field !== null) {
            return $this->where($field, $value)->first();
        }
        $id = Hashid::decode((string) $value);
        return $id === null ? null : $this->where($this->getKeyName(), $id)->first();
    }

    /** Tiện ích: lấy hashid để nhúng vào JSON cho frontend dựng URL. */
    public function hashid(): string
    {
        return Hashid::encode((int) $this->getKey());
    }
}
