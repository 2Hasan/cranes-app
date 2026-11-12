<?php

namespace App\Services;

use App\Enums\FaultStatus;
use App\Models\Crane;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class CraneAttention
{
    public const RANK_LATE = 0;

    public const RANK_SOON = 1;

    public const RANK_FAULTS = 2;

    public const RANK_OTHER = 3;

    public function __construct(private ServiceSchedule $schedule) {}

    /**
     * Ids of the cranes the user can see, most urgent first:
     * overdue service, then service due soon, then open faults, then the rest.
     *
     * @return list<int>
     */
    public function sortedIds(User $user): array
    {
        $ranked = [];

        foreach ($this->visibleCranes($user)->get() as $crane) {
            $ranked[] = [
                'id' => $crane->getKey(),
                'rank' => $this->rankFor($crane),
                'code' => $crane->code,
            ];
        }

        usort($ranked, fn (array $first, array $second): int => [$first['rank'], $first['code']] <=> [$second['rank'], $second['code']]);

        return array_column($ranked, 'id');
    }

    /**
     * Ids of the visible cranes whose service state matches the given schedule state.
     *
     * @return list<int>
     */
    public function idsWithServiceState(User $user, string $state): array
    {
        $ids = [];

        foreach ($this->visibleCranes($user)->get() as $crane) {
            if ($this->schedule->for($crane)['state'] === $state) {
                $ids[] = $crane->getKey();
            }
        }

        return $ids;
    }

    /**
     * Ids of the visible cranes that have at least one fault not yet resolved.
     *
     * @return list<int>
     */
    public function idsWithOpenFaults(User $user): array
    {
        return $this->visibleCranes($user)
            ->whereHas('faults', fn (Builder $faults) => $faults->where('status', '!=', FaultStatus::Resolved->value))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    private function rankFor(Crane $crane): int
    {
        $state = $this->schedule->for($crane)['state'];

        return match (true) {
            $state === ServiceSchedule::STATE_LATE => self::RANK_LATE,
            $state === ServiceSchedule::STATE_SOON => self::RANK_SOON,
            $crane->faults()->where('status', '!=', FaultStatus::Resolved->value)->exists() => self::RANK_FAULTS,
            default => self::RANK_OTHER,
        };
    }

    /** @return Builder<Crane> */
    private function visibleCranes(User $user): Builder
    {
        return Crane::query()->visibleTo($user)->orderBy('code');
    }
}
