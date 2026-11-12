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
     * Corrects a mistyped latest reading. The corrected value may not go below the reading before it.
     *
     * @throws ValidationException
     */
    public function correctLatest(Crane $crane, int $km, bool $allowBelowPrevious = false): KmReading
    {
        return DB::transaction(function () use ($crane, $km): KmReading {
            $lockedCrane = Crane::query()->lockForUpdate()->findOrFail($crane->getKey());

            $latest = KmReading::query()
                ->where('crane_id', $lockedCrane->getKey())
                ->latest('recorded_at')
                ->latest('id')
                ->first();

            if ($latest === null) {
                throw ValidationException::withMessages(['km' => 'لا توجد قراءة لتصحيحها.']);
            }

            $previous = KmReading::query()
                ->where('crane_id', $lockedCrane->getKey())
                ->whereKeyNot($latest->getKey())
                ->latest('recorded_at')
                ->latest('id')
                ->first();

            if (! $allowBelowPrevious && $previous !== null && $km < $previous->km) {
                throw ValidationException::withMessages([
                    'km' => "القراءة ({$km}) أقل من القراءة قبلها ({$previous->km}).",
                ]);
            }

            $latest->update(['km' => $km]);
            $lockedCrane->update(['current_km' => $km]);

            return $latest;
        });
    }

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
