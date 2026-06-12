<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục kho (sheet ICD). */
class TruckingWarehouse extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
