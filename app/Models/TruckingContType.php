<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục loại container. */
class TruckingContType extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
