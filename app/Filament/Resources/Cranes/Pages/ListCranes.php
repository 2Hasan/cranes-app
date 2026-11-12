<?php

namespace App\Filament\Resources\Cranes\Pages;

use App\Filament\Resources\Cranes\CraneResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCranes extends ListRecords
{
    protected static string $resource = CraneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('allReport')
                ->label('تقرير كل الرافعات')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn (): string => route('cranes.all-report'), shouldOpenInNewTab: true)
                ->visible(fn (): bool => auth()->user()?->can('report.export') ?? false),
            CreateAction::make(),
        ];
    }
}
