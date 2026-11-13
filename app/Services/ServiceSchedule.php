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
     * The latest periodic service stores the next due date and meter it was given; without
     * those, the due date and meter come from the crane's service cycle.
     *
     * @return array{state: string, date_state: string, km_state: string, next_date: ?string, days_left: ?int, next_km: ?int, km_left: ?int, date_progress: ?float, km_progress: ?float}
     */
    public function for(Crane $crane): array
    {
        $lastPeriodic = $crane->maintenanceRecords()
            ->where('type', MaintenanceType::Periodic)
            ->orderByDesc('performed_on')
            ->orderByDesc('id')
            ->first();

        $lastDate = $lastPeriodic?->performed_on ?? $crane->last_service_date;
        $lastKm = $lastPeriodic?->km ?? $crane->last_service_km;

        $nextDate = $this->nextDate($lastPeriodic?->next_service_date, $lastDate, $crane);
        $nextKm = $this->nextKm($lastPeriodic?->next_service_km, $lastKm, $crane);

        $dateState = self::STATE_NONE;
        $daysLeft = null;
        $dateProgress = null;

        if ($nextDate !== null) {
            $today = now()->startOfDay();
            $daysLeft = (int) $today->diffInDays($nextDate, false);
            $dateState = match (true) {
                $daysLeft < 0 => self::STATE_LATE,
                $daysLeft <= self::SOON_DAYS => self::STATE_SOON,
                default => self::STATE_OK,
            };

            if ($crane->service_interval_days) {
                // The cycle started one interval before the due date; progress is how far today is into it.
                $cycleStart = $nextDate->copy()->subDays($crane->service_interval_days);
                $dateProgress = $this->progress((int) $cycleStart->diffInDays($today, false), $crane->service_interval_days);
            }
        }

        $kmState = self::STATE_NONE;
        $kmLeft = null;
        $kmProgress = null;

        if ($nextKm !== null) {
            $kmLeft = $nextKm - $crane->current_km;
            $kmState = match (true) {
                $kmLeft < 0 => self::STATE_LATE,
                $kmLeft <= ($crane->service_interval_km ?? $nextKm) * self::SOON_KM_RATIO => self::STATE_SOON,
                default => self::STATE_OK,
            };

            if ($crane->service_interval_km) {
                // The cycle started one interval before the due meter.
                $kmProgress = $this->progress($crane->current_km - ($nextKm - $crane->service_interval_km), $crane->service_interval_km);
            }
        }

        return [
            'state' => $this->worst($dateState, $kmState),
            'date_state' => $dateState,
            'km_state' => $kmState,
            'next_date' => $nextDate?->toDateString(),
            'days_left' => $daysLeft,
            'next_km' => $nextKm,
            'km_left' => $kmLeft,
            'date_progress' => $dateProgress,
            'km_progress' => $kmProgress,
        ];
    }

    /**
     * Human text for the crane card, e.g. "بعد 3 أيام · تجاوزت 1,082 كم".
     *
     * @param  array{days_left: ?int, km_left: ?int}  $schedule
     */
    public function summary(array $schedule): string
    {
        $parts = [];

        if ($schedule['days_left'] !== null) {
            $days = $schedule['days_left'];
            $parts[] = match (true) {
                $days < 0 => 'متأخرة '.$this->dayCount(-$days),
                $days === 0 => 'الصيانة اليوم',
                default => 'بعد '.$this->dayCount($days),
            };
        }

        if ($schedule['km_left'] !== null) {
            $km = $schedule['km_left'];
            $parts[] = $km < 0
                ? 'تجاوزت '.number_format(-$km).' كم'
                : 'بعد '.number_format($km).' كم';
        }

        return $parts !== [] ? implode(' · ', $parts) : 'لم تُحدد دورة الصيانة';
    }

    private function nextDate(?string $storedDate, ?string $lastDate, Crane $crane): ?Carbon
    {
        if ($storedDate !== null) {
            return Carbon::parse($storedDate)->startOfDay();
        }

        if ($lastDate !== null && $crane->service_interval_days) {
            return Carbon::parse($lastDate)->addDays($crane->service_interval_days)->startOfDay();
        }

        return null;
    }

    private function nextKm(?int $storedKm, ?int $lastKm, Crane $crane): ?int
    {
        if ($storedKm !== null) {
            return $storedKm;
        }

        if ($lastKm !== null && $crane->service_interval_km) {
            return $lastKm + $crane->service_interval_km;
        }

        return null;
    }

    private function dayCount(int $days): string
    {
        return match (true) {
            $days === 1 => 'يوم',
            $days === 2 => 'يومين',
            $days >= 3 && $days <= 10 => $days.' أيام',
            default => $days.' يومًا',
        };
    }

    /** Share of the service cycle already used, between 0 and 1. */
    private function progress(int $used, int $span): float
    {
        if ($span <= 0) {
            return 0.0;
        }

        return max(0, min(1, $used / $span));
    }

    private function worst(string $first, string $second): string
    {
        $rank = [self::STATE_NONE => 0, self::STATE_OK => 1, self::STATE_SOON => 2, self::STATE_LATE => 3];

        return $rank[$first] >= $rank[$second] ? $first : $second;
    }
}
