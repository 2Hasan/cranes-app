<?php

namespace App\Services;

use App\Enums\MaintenanceType;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;

class ServiceRecorder
{
    /**
     * Logs a periodic service done today. The date and meter typed are the next due
     * service: the date it is due, and the odometer reading at which the crane needs
     * service even if that date has not arrived yet. The odometer itself is not changed.
     */
    public function record(Crane $crane, Carbon $nextServiceDate, int $nextServiceKm, User $recorder): MaintenanceRecord
    {
        return MaintenanceRecord::create([
            'crane_id' => $crane->getKey(),
            'created_by' => $recorder->getKey(),
            'type' => MaintenanceType::Periodic,
            'performed_on' => now()->toDateString(),
            'km' => $crane->fresh()->current_km,
            'next_service_date' => $nextServiceDate->toDateString(),
            'next_service_km' => $nextServiceKm,
            'description' => 'سيرفس دوري',
        ]);
    }
}
