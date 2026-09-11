<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một dòng bảng giá (1 tuyến) của 1 khách hàng.
 * Giá theo LOẠI CONT: `prices` = {khóa loại cont => giá TỔNG cước + dầu}, vd {"20DC": 4480000, "40HC": 5130000}.
 * Khóa chung "20FT"/"40FT"/"45FT" áp cho mọi loại cont cùng cỡ chưa có cột riêng (dữ liệu cũ backfill về đây).
 * 4 cột trans_fee_x / fuel_fee_x là di sản (không còn ghi), giữ trong DB để đối chiếu.
 */
class TruckingPriceRow extends Model
{
    protected $fillable = [
        'customer_id', 'price_book_id', 'location_id', 'loc', 'conn', 'kind',
        'from', 'to1', 'to2', 'to3', 'to4',
        'distance', 'prices', 'sort',
    ];

    protected $casts = [
        'prices' => 'array',
        'sort'   => 'integer',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(TruckingCustomer::class, 'customer_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(TruckingLocation::class, 'location_id');
    }

    public function priceBook(): BelongsTo
    {
        return $this->belongsTo(TruckingPriceBook::class, 'price_book_id');
    }
}
