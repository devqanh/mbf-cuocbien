<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục khoản lương thêm cho lái xe (thưởng, phụ cấp…) — link ở Phí xe nội bộ. */
class TruckingSalaryItem extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
