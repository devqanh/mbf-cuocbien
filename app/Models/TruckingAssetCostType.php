<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục loại chi phí TÀI SẢN (bảo trì, sửa chữa, vật tư…) — link ở Quản lý tài sản (tab Chi phí) để nhóm báo cáo. */
class TruckingAssetCostType extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
