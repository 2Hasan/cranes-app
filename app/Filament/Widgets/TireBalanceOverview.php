<?php

namespace App\Filament\Widgets;

use App\Services\TireStock;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TireBalanceOverview extends StatsOverviewWidget
{
    public static function canView(): bool
    {
        return auth()->user()?->can('tires.manage') ?? false;
    }

    /** @return array<Stat> */
    protected function getStats(): array
    {
        return app(TireStock::class)->balances()
            ->map(fn (array $row): Stat => Stat::make($row['site']->name, $row['balance'])
                ->description($row['owner']->name)
                ->icon('heroicon-o-circle-stack')
                ->color('gray'))
            ->values()
            ->all();
    }
}
