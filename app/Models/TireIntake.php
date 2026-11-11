<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TireIntake extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'tire_owner_id',
        'quantity',
        'received_on',
        'note',
        'created_by',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'received_on' => 'date',
        ];
    }

    /** @return BelongsTo<TireOwner, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(TireOwner::class, 'tire_owner_id');
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }
}
