<?php

namespace App\Services;

use App\Enums\MaintenanceType;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ServiceRecorder
{
    public function __construct(private KmRecorder $odometer) {}

    /**
     * Logs a periodic service done on a given date at a given odometer reading.
     * The next due date and kilometres are worked out by ServiceSchedule from this record.
     */
    public function record(Crane $crane, Carbon $performedOn, int $km, User $recorder): MaintenanceRecord
    {
        return DB::transaction(function () use ($crane, $performedOn, $km, $recorder): MaintenanceRecord {
            $record = MaintenanceRecord::create([
                'crane_id' => $crane->getKey(),
                'created_by' => $recorder->getKey(),
                'type' => MaintenanceType::Periodic,
                'performed_on' => $performedOn->toDateString(),
                'km' => $km,
                'description' => 'سيرفس دوري',
            ]);

            if ($km > $crane->fresh()->current_km) {
                $this->odometer->record($crane, $km, $recorder);
            }

            return $record;
        });
    }
}
