<?php

namespace App\Models;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use Database\Factories\FaultFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(FaultFactory::class)]
class Fault extends Model
{
    /** @use HasFactory<FaultFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'crane_id',
        'title',
        'description',
        'severity',
        'status',
        'reported_by',
        'assigned_to',
        'maintenance_record_id',
        'reported_at',
        'resolved_at',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'severity' => FaultSeverity::class,
            'status' => FaultStatus::class,
            'reported_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Crane, $this> */
    public function crane(): BelongsTo
    {
        return $this->belongsTo(Crane::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<MaintenanceRecord, $this> */
    public function maintenanceRecord(): BelongsTo
    {
        return $this->belongsTo(MaintenanceRecord::class);
    }
}
