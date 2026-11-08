<?php

namespace App\Filament\Widgets;

use App\Enums\CraneStatus;
use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\Crane;
use App\Models\Fault;
use App\Services\ServiceSchedule;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CraneStatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('crane.view') ?? false;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        $cranes = Crane::query()->visibleTo(auth()->user());
        $cranesByStatus = (clone $cranes)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $late = 0;
        $soon = 0;
        foreach ((clone $cranes)->get() as $crane) {
            $state = app(ServiceSchedule::class)->for($crane)['state'];

            $late += $state === ServiceSchedule::STATE_LATE ? 1 : 0;
            $soon += $state === ServiceSchedule::STATE_SOON ? 1 : 0;
        }

        $criticalFaults = Fault::query()
            ->whereIn('crane_id', (clone $cranes)->select('id'))
            ->whereIn('status', [FaultStatus::Open, FaultStatus::InProgress])
            ->whereIn('severity', [FaultSeverity::High, FaultSeverity::Critical])
            ->count();

        return [
            Stat::make('إجمالي الرافعات', (clone $cranes)->count())
                ->icon('heroicon-o-truck')
                ->color('gray'),
            Stat::make('تعمل', $cranesByStatus[CraneStatus::Working->value] ?? 0)
                ->icon('heroicon-o-check-circle')
                ->color('success'),
            Stat::make('تحت الصيانة', $cranesByStatus[CraneStatus::UnderMaintenance->value] ?? 0)
                ->icon('heroicon-o-wrench-screwdriver')
                ->color('warning'),
            Stat::make('متوقفة', $cranesByStatus[CraneStatus::Stopped->value] ?? 0)
                ->icon('heroicon-o-pause-circle')
                ->color('gray'),
            Stat::make('صيانة متأخرة', $late)
                ->description('تحتاج صيانة فوراً')
                ->icon('heroicon-o-exclamation-triangle')
                ->color($late > 0 ? 'danger' : 'success'),
            Stat::make('صيانة قريبة', $soon)
                ->description('خلال أسبوعين أو 10% من الدورة')
                ->icon('heroicon-o-clock')
                ->color($soon > 0 ? 'warning' : 'success'),
            Stat::make('أعطال مفتوحة مهمة', $criticalFaults)
                ->description('عالية أو حرجة ولم تُحل')
                ->icon('heroicon-o-bolt')
                ->color($criticalFaults > 0 ? 'danger' : 'success'),
        ];
    }

    /** @return int|array<string, int|null> */
    protected function getColumns(): int|array
    {
        return ['default' => 2, 'md' => 3, 'xl' => 4];
    }
}
