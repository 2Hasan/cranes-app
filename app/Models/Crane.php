<?php

namespace App\Models;

use App\Enums\CraneStatus;
use Database\Factories\CraneFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(CraneFactory::class)]
class Crane extends Model
{
    /** @use HasFactory<CraneFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'code',
        'name',
        'type',
        'capacity_ton',
        'serial_number',
        'manufacture_year',
        'site_id',
        'status',
        'current_km',
        'service_interval_days',
        'service_interval_km',
        'last_service_date',
        'last_service_km',
        'notes',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => CraneStatus::class,
            'capacity_ton' => 'decimal:2',
            'current_km' => 'integer',
            'service_interval_days' => 'integer',
            'service_interval_km' => 'integer',
            'last_service_date' => 'date',
            'last_service_km' => 'integer',
            'version' => 'integer',
        ];
    }

    /** @return BelongsTo<Site, $this> */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /** @return HasMany<KmReading, $this> */
    public function kmReadings(): HasMany
    {
        return $this->hasMany(KmReading::class);
    }

    /** @return HasMany<MaintenanceRecord, $this> */
    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    /** @return HasMany<Fault, $this> */
    public function faults(): HasMany
    {
        return $this->hasMany(Fault::class);
    }

    /**
     * Limits cranes to the user's site unless the user may view every site.
     *
     * @param  Builder<Crane>  $query
     * @return Builder<Crane>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('sites.view_all')) {
            return $query;
        }

        return $query->where('site_id', $user->site_id);
    }
}
