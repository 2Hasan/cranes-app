<?php

namespace App\Services;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\Crane;
use App\Models\Fault;
use App\Models\User;
use App\Notifications\MaintenanceDigestNotification;
use App\Notifications\SevereFaultNotification;
use Illuminate\Support\Collection;

class MaintenanceAlerts
{
    public function __construct(private ServiceSchedule $schedule) {}

    /**
     * Sends each active user one summary of what needs attention on the cranes they can see.
     * Users with nothing to report receive nothing.
     *
     * @return int number of users notified
     */
    public function sendDailyDigest(): int
    {
        $notified = 0;

        $this->activeUsersWhoCanViewCranes()->each(function (User $user) use (&$notified): void {
            $cranes = Crane::query()->visibleTo($user)->get();

            $lateServices = 0;
            $soonServices = 0;

            foreach ($cranes as $crane) {
                $state = $this->schedule->for($crane)['state'];

                if ($state === ServiceSchedule::STATE_LATE) {
                    $lateServices++;
                } elseif ($state === ServiceSchedule::STATE_SOON) {
                    $soonServices++;
                }
            }

            $openSevereFaults = Fault::query()
                ->whereIn('crane_id', $cranes->pluck('id'))
                ->whereIn('status', [FaultStatus::Open, FaultStatus::InProgress])
                ->whereIn('severity', [FaultSeverity::High, FaultSeverity::Critical])
                ->count();

            if ($lateServices + $soonServices + $openSevereFaults === 0) {
                return;
            }

            $user->notify(new MaintenanceDigestNotification($lateServices, $soonServices, $openSevereFaults));
            $notified++;
        });

        return $notified;
    }

    /**
     * Alerts everyone who can see the crane when a high or critical fault is reported.
     * The person who reported it is not notified.
     *
     * @return int number of users notified
     */
    public function notifySevereFault(Fault $fault): int
    {
        $fault->loadMissing('crane');

        $recipients = $this->activeUsersWhoCanViewCranes()
            ->reject(fn (User $user): bool => $user->getKey() === $fault->reported_by)
            ->filter(fn (User $user): bool => Crane::query()
                ->visibleTo($user)
                ->whereKey($fault->crane_id)
                ->exists());

        $recipients->each(fn (User $user) => $user->notify(new SevereFaultNotification($fault)));

        return $recipients->count();
    }

    /**
     * @return Collection<int, User>
     */
    private function activeUsersWhoCanViewCranes(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->get()
            ->filter(fn (User $user): bool => $user->can('crane.view'))
            ->values();
    }
}
