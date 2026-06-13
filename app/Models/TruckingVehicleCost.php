<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Chi phí xe (cố định / định kỳ) + trạng thái thanh toán & duyệt. */
class TruckingVehicleCost extends Model
{
    protected $fillable = ['vehicle_id', 'name', 'invoice_no', 'kind', 'spend_date', 'due_date', 'amount', 'current_km', 'supplier', 'note', 'paid', 'paid_date', 'paid_method', 'paid_ref', 'paid_note', 'approved', 'photos', 'sort'];
    protected $casts = ['spend_date' => 'date', 'due_date' => 'date', 'paid_date' => 'date', 'amount' => 'decimal:2', 'current_km' => 'decimal:2', 'paid' => 'boolean', 'approved' => 'boolean', 'photos' => 'array', 'sort' => 'integer'];
}
