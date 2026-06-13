<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục loại chi phí xe (bảo dưỡng, sửa chữa, đăng kiểm…) — link ở Quản lý xe để nhóm báo cáo. */
class TruckingVehicleCostType extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
