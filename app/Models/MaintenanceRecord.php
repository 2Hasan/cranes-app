<?php

namespace App\Models;

use App\Enums\MaintenanceType;
use App\Enums\RepairResult;
use Database\Factories\MaintenanceRecordFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(MaintenanceRecordFactory::class)]
class MaintenanceRecord extends Model
{
    /** @use HasFactory<MaintenanceRecordFactory> */
    use HasFactory, SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'crane_id',
        'technician_name',
        'created_by',
        'type',
        'repair_result',
        'performed_on',
        'km',
        'next_service_date',
        'next_service_km',
        'description',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'type' => MaintenanceType::class,
            'repair_result' => RepairResult::class,
            'performed_on' => 'date',
            'km' => 'integer',
            'next_service_date' => 'date',
            'next_service_km' => 'integer',
        ];
    }

    /** @return BelongsTo<Crane, $this> */
    public function crane(): BelongsTo
    {
        return $this->belongsTo(Crane::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<Fault, $this> */
    public function resolvedFaults(): HasMany
    {
        return $this->hasMany(Fault::class);
    }
}
