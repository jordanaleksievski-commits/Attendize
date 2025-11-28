<?php

namespace App\Models\Pos;

use App\Models\MyBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosOrderAdjustment extends MyBaseModel
{
    protected $table = 'pos_order_adjustments';

    protected $fillable = [
        'account_id',
        'order_id',
        'order_item_id',
        'type',
        'amount',
        'user_id',
        'reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
        'amount' => 'float',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PosOrderItem::class, 'order_item_id');
    }
}
