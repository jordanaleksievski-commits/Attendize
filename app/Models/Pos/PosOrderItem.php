<?php

namespace App\Models\Pos;

use App\Models\MyBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosOrderItem extends MyBaseModel
{
    use SoftDeletes;

    protected $table = 'pos_order_items';

    protected $fillable = [
        'order_id',
        'name',
        'quantity',
        'unit_price',
        'discount_amount',
        'total',
        'notes',
        'voided_by',
        'voided_at',
        'transferred_to_order_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'float',
        'discount_amount' => 'float',
        'total' => 'float',
        'voided_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }
}
