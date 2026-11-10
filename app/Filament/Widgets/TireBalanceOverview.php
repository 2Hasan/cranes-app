<?php

namespace App\Filament\Widgets;

use App\Models\Site;
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
        $stock = app(TireStock::class);

        return Site::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Site $site): Stat => Stat::make($site->name, $stock->balance($site))
                ->description('مطاط موجود')
                ->icon('heroicon-o-circle-stack')
                ->color('gray'))
            ->all();
    }
}
