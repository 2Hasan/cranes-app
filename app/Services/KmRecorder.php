<?php

namespace App\Services;

use App\Models\Crane;
use App\Models\KmReading;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KmRecorder
{
    /**
     * Stores an odometer reading and updates the crane's current km.
     * Readings may not go below the last recorded value.
     *
     * @throws ValidationException
     */
    public function record(Crane $crane, int $km, User $recorder, ?Carbon $recordedAt = null): KmReading
    {
        return DB::transaction(function () use ($crane, $km, $recorder, $recordedAt): KmReading {
            $lockedCrane = Crane::query()->lockForUpdate()->findOrFail($crane->getKey());

            if ($km < $lockedCrane->current_km) {
                throw ValidationException::withMessages([
                    'km' => "القراءة ({$km}) أقل من آخر قراءة مسجلة ({$lockedCrane->current_km}).",
                ]);
            }

            $reading = KmReading::create([
                'crane_id' => $lockedCrane->getKey(),
                'recorded_by' => $recorder->getKey(),
                'km' => $km,
                'recorded_at' => $recordedAt ?? now(),
            ]);

            $lockedCrane->update(['current_km' => $km]);

            return $reading;
        });
    }
}
