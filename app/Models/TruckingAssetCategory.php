<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục loại tài sản — dùng cho Combo "Loại tài sản" ở Quản lý tài sản. */
class TruckingAssetCategory extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
