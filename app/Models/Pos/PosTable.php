<?php

namespace App\Models\Pos;

use App\Models\MyBaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosTable extends MyBaseModel
{
    use SoftDeletes;

    protected $table = 'pos_tables';

    protected $fillable = [
        'account_id',
        'name',
        'capacity',
        'status',
        'notes',
        'merged_into_table_id',
    ];

    protected $casts = [
        'capacity' => 'integer',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'table_id');
    }

    public function activeOrder(): HasOne
    {
        return $this->hasOne(PosOrder::class, 'table_id')->whereNull('merged_into_order_id')->where('status', 'open');
    }
}
