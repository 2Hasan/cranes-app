<?php

namespace App\Services;

use App\Enums\MaintenanceType;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ServiceRecorder
{
    public function __construct(private KmRecorder $odometer) {}

    /**
     * Logs a periodic service. The reading typed is the crane's odometer now; the service
     * itself is stored one cycle back (typed − service interval), so with a 5,000 km cycle
     * a typed 10,000 is recorded as a service at 5,000. ServiceSchedule then works out the
     * next due from that record.
     *
     * @throws ValidationException when the reading is below the odometer or below one cycle
     */
    public function record(Crane $crane, Carbon $performedOn, int $typedKm, User $recorder): MaintenanceRecord
    {
        $crane = $crane->fresh();
        $serviceKm = $crane->service_interval_km ? $typedKm - $crane->service_interval_km : $typedKm;

        if ($typedKm < $crane->current_km) {
            throw ValidationException::withMessages([
                'km' => "العداد ({$typedKm}) أقل من عداد الرافعة الحالي ({$crane->current_km}).",
            ]);
        }

        if ($serviceKm < 0) {
            throw ValidationException::withMessages([
                'km' => "العداد ({$typedKm}) أقل من دورة الصيانة ({$crane->service_interval_km} كم).",
            ]);
        }

        return DB::transaction(function () use ($crane, $performedOn, $typedKm, $serviceKm, $recorder): MaintenanceRecord {
            $record = MaintenanceRecord::create([
                'crane_id' => $crane->getKey(),
                'created_by' => $recorder->getKey(),
                'type' => MaintenanceType::Periodic,
                'performed_on' => $performedOn->toDateString(),
                'km' => $serviceKm,
                'description' => 'سيرفس دوري',
            ]);

            if ($typedKm > $crane->current_km) {
                $this->odometer->record($crane, $typedKm, $recorder);
            }

            return $record;
        });
    }
}
