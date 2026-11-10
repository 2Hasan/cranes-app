<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireReturn extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'tire_transfer_id',
        'returned_quantity',
        'damaged_quantity',
        'returned_by',
        'returned_at',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'returned_quantity' => 'integer',
            'damaged_quantity' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TireTransfer, $this> */
    public function transfer(): BelongsTo
    {
        return $this->belongsTo(TireTransfer::class, 'tire_transfer_id');
    }
}
