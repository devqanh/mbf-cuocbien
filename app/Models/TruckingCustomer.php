<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Khách hàng trucking — master data (MST, liên hệ, hạn TT, bảng giá). */
class TruckingCustomer extends Model
{
    protected $fillable = [
        'name', 'short_name', 'tax_code', 'phone', 'contact', 'email',
        'term_days', 'address', 'note',
    ];

    protected $casts = ['term_days' => 'integer'];

    /**
     * Khách nguồn mà khách này DÙNG CHUNG bảng giá (null = bảng giá riêng). Chỉ 1 cấp: khách nguồn không được
     * tự dùng chung của khách khác. Không nằm trong $fillable để lưu danh mục khách không vô tình xóa liên kết.
     */
    public function priceSource(): BelongsTo
    {
        return $this->belongsTo(self::class, 'price_source_id');
    }

    public function priceRows(): HasMany
    {
        return $this->hasMany(TruckingPriceRow::class, 'customer_id')->orderBy('sort');
    }

    public function priceBooks(): HasMany
    {
        return $this->hasMany(TruckingPriceBook::class, 'customer_id')->orderBy('period_from');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(TruckingShipment::class, 'customer_id');
    }
}
