<?php

namespace App\Services;

use App\Enums\MaintenanceType;
use App\Models\Crane;
use Illuminate\Support\Carbon;

class ServiceSchedule
{
    public const STATE_NONE = 'none';

    public const STATE_OK = 'ok';

    public const STATE_SOON = 'soon';

    public const STATE_LATE = 'late';

    private const SOON_DAYS = 14;

    private const SOON_KM_RATIO = 0.1;

    /**
     * Works out the next service due by date and/or by kilometres.
     * The baseline is the latest periodic record, or the crane's initial values.
     *
     * @return array{state: string, date_state: string, km_state: string, next_date: ?string, days_left: ?int, next_km: ?int, km_left: ?int}
     */
    public function for(Crane $crane): array
    {
        $lastPeriodic = $crane->maintenanceRecords()
            ->where('type', MaintenanceType::Periodic)
            ->orderByDesc('performed_on')
            ->first();

        $lastDate = $lastPeriodic?->performed_on ?? $crane->last_service_date;
        $lastKm = $lastPeriodic?->km ?? $crane->last_service_km;

        $dateState = self::STATE_NONE;
        $nextDate = null;
        $daysLeft = null;

        if ($lastDate !== null && $crane->service_interval_days) {
            $nextDate = Carbon::parse($lastDate)->addDays($crane->service_interval_days)->startOfDay();
            $daysLeft = (int) now()->startOfDay()->diffInDays($nextDate, false);
            $dateState = match (true) {
                $daysLeft < 0 => self::STATE_LATE,
                $daysLeft <= self::SOON_DAYS => self::STATE_SOON,
                default => self::STATE_OK,
            };
        }

        $kmState = self::STATE_NONE;
        $nextKm = null;
        $kmLeft = null;

        if ($lastKm !== null && $crane->service_interval_km) {
            $nextKm = $lastKm + $crane->service_interval_km;
            $kmLeft = $nextKm - $crane->current_km;
            $kmState = match (true) {
                $kmLeft < 0 => self::STATE_LATE,
                $kmLeft <= $crane->service_interval_km * self::SOON_KM_RATIO => self::STATE_SOON,
                default => self::STATE_OK,
            };
        }

        return [
            'state' => $this->worst($dateState, $kmState),
            'date_state' => $dateState,
            'km_state' => $kmState,
            'next_date' => $nextDate?->toDateString(),
            'days_left' => $daysLeft,
            'next_km' => $nextKm,
            'km_left' => $kmLeft,
        ];
    }

    private function worst(string $first, string $second): string
    {
        $rank = [self::STATE_NONE => 0, self::STATE_OK => 1, self::STATE_SOON => 2, self::STATE_LATE => 3];

        return $rank[$first] >= $rank[$second] ? $first : $second;
    }
}
