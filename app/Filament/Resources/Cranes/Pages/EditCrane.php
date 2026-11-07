<?php

namespace App\Filament\Resources\Cranes\Pages;

use App\Filament\Resources\Cranes\CraneResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCrane extends EditRecord
{
    protected static string $resource = CraneResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // The odometer only changes through KmRecorder, never through the edit form.
        unset($data['initial_km']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label('تقرير PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn (): string => route('cranes.report', $this->getRecord()), shouldOpenInNewTab: true)
                ->visible(fn (): bool => auth()->user()?->can('report.export') ?? false),
            DeleteAction::make(),
        ];
    }
}
