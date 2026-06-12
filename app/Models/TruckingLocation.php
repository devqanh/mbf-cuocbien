<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục địa điểm (depot, cảng, ICD, KCN) + ký hiệu viết tắt. */
class TruckingLocation extends Model
{
    protected $fillable = ['name', 'code', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
