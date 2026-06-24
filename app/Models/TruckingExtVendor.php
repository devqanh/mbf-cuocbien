<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục đơn vị xe ngoài (nhà xe thuê) — dùng cho lô "Thuê xe ngoài" + Bảng kê xe ngoài. */
class TruckingExtVendor extends Model
{
    protected $fillable = ['name', 'phone', 'note', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
