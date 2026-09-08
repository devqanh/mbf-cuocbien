<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục loại chi phí VĂN PHÒNG (thuê VP, điện nước, VPP, lương khối VP…) — link ở tab Chi phí văn phòng để nhóm báo cáo. */
class TruckingOfficeCostType extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
