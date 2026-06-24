<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục khoản chi phí (gợi ý + đơn giá mặc định). */
class TruckingCostItem extends Model
{
    protected $fillable = ['name', 'default_price', 'color', 'auto', 'sort'];
    protected $casts = ['default_price' => 'decimal:2', 'sort' => 'integer', 'auto' => 'boolean'];
}
