<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục bên thanh toán / người chi. */
class TruckingPayer extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
