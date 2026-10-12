<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * BẢNG PHÍ TUYẾN theo thời gian (như price book ở Bảng giá): mỗi bảng = 1 bộ phí tuyến đầy đủ, áp dụng
 * từ `from_date` trở đi (null = bảng mặc định, áp mọi thời điểm). Chuyến ngày D dùng bảng có from_date
 * lớn nhất ≤ D. Tạo bảng mới = sao chép toàn bộ tuyến của bảng đang chọn rồi sửa giá.
 */
class TruckingRouteFeeBook extends Model
{
    protected $fillable = ['label', 'from_date', 'regulation', 'sort'];

    // regulation = quy chế lương chuyến đi kèm bảng (App\Support\SalaryZones::cleanRegulation)
    protected $casts = ['from_date' => 'date', 'regulation' => 'array', 'sort' => 'integer'];

    public function fees(): HasMany
    {
        return $this->hasMany(TruckingRouteFee::class, 'book_id')->orderBy('sort')->orderBy('id');
    }
}
