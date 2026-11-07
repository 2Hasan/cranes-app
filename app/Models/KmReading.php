<?php

namespace App\Models;

use Database\Factories\KmReadingFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(KmReadingFactory::class)]
class KmReading extends Model
{
    /** @use HasFactory<KmReadingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crane_id',
        'recorded_by',
        'km',
        'recorded_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'km' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Crane, $this> */
    public function crane(): BelongsTo
    {
        return $this->belongsTo(Crane::class);
    }

    /** @return BelongsTo<User, $this> */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
