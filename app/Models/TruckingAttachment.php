<?php

namespace App\Models;

use App\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** File tập trung (polymorphic) — tài liệu lái xe/xe, ảnh phiếu chi. Mỗi file lưu kèm disk để dễ migrate S3. */
class TruckingAttachment extends Model
{
    use HasHashid;

    protected $fillable = ['owner_type', 'owner_id', 'group', 'disk', 'path', 'name', 'type', 'mime', 'size', 'sort'];
    protected $casts = ['size' => 'integer', 'sort' => 'integer'];

    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime, 'image/');
    }
}
