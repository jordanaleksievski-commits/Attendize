<?php

namespace App\Models\Pos;

use App\Models\MyBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosOrder extends MyBaseModel
{
    use SoftDeletes;

    protected $table = 'pos_orders';

    protected $fillable = [
        'account_id',
        'table_id',
        'shift_id',
        'reference',
        'status',
        'covers',
        'subtotal',
        'discount_total',
        'total',
        'opened_by',
        'closed_by',
        'merged_into_order_id',
        'closed_at',
    ];

    protected $casts = [
        'covers' => 'integer',
        'subtotal' => 'float',
        'discount_total' => 'float',
        'total' => 'float',
        'closed_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(PosTable::class, 'table_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(PosShift::class, 'shift_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PosOrderItem::class, 'order_id');
    }

    public function adjustments(): HasMany
    {
        return $this->hasMany(PosOrderAdjustment::class, 'order_id');
    }

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function refreshTotals(): void
    {
        $subtotal = $this->items()->whereNull('voided_at')->sum('total');
        $discounts = $this->items()->whereNull('voided_at')->sum('discount_amount');

        $this->subtotal = $subtotal;
        $this->discount_total = $discounts;
        $this->total = $subtotal - $discounts;
        $this->save();
    }
}
