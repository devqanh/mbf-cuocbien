<?php

namespace App\Models;

use App\Concerns\HasHashid;
use Illuminate\Database\Eloquent\Model;

/** Danh mục lái xe — kèm hồ sơ (SĐT, ngày sinh/vào công ty, tài khoản, tài liệu). */
class TruckingDriver extends Model
{
    use HasHashid;

    protected $fillable = [
        'name', 'sort', 'phones', 'birthday', 'joined_date', 'bank_accounts', 'documents',
    ];

    protected $casts = [
        'sort'          => 'integer',
        'birthday'      => 'date',
        'joined_date'   => 'date',
        'phones'        => 'array',
        'bank_accounts' => 'array',
        'documents'     => 'array',
    ];
}
