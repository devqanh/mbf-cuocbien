<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục khoản thu/chi hộ (gợi ý + đơn giá mặc định). */
class TruckingChohoItem extends Model
{
    protected $fillable = ['name', 'default_price', 'sort'];
    protected $casts = ['default_price' => 'decimal:2', 'sort' => 'integer'];
}
