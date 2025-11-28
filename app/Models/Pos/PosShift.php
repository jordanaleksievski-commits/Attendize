<?php

namespace App\Models\Pos;

use App\Models\MyBaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PosShift extends MyBaseModel
{
    protected $table = 'pos_shifts';

    protected $fillable = [
        'account_id',
        'user_id',
        'started_at',
        'ended_at',
        'opening_float',
        'closing_float',
        'notes',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'opening_float' => 'float',
        'closing_float' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }
}
