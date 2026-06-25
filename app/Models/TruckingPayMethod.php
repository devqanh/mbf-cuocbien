<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Danh mục HÌNH THỨC THANH TOÁN (Chuyển khoản, Tiền mặt, Vietinbank, Bank công ty, Cá nhân…) — dùng cho phiếu chi xe/tài sản; cấu hình ở Cài đặt để sau lọc. */
class TruckingPayMethod extends Model
{
    protected $fillable = ['name', 'sort'];
    protected $casts = ['sort' => 'integer'];
}
