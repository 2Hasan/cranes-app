<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TireTransfer extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'from_site_id',
        'to_site_id',
        'quantity',
        'handled_by',
        'moved_at',
        'note',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'moved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function fromSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'from_site_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function toSite(): BelongsTo
    {
        return $this->belongsTo(Site::class, 'to_site_id');
    }

    /** @return HasMany<TireReturn, $this> */
    public function returns(): HasMany
    {
        return $this->hasMany(TireReturn::class, 'tire_transfer_id');
    }
}
