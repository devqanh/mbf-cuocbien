<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục lái xe. */
class TruckingDriver extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
