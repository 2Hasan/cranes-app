<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireOwnerReturn extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'tire_owner_id',
        'quantity',
        'returned_at',
        'note',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'returned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return BelongsTo<TireOwner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(TireOwner::class, 'tire_owner_id');
    }
}
