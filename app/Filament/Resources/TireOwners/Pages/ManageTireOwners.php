<?php

namespace App\Filament\Resources\TireOwners\Pages;

use App\Filament\Resources\TireOwners\TireOwnerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageTireOwners extends ManageRecords
{
    protected static string $resource = TireOwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('إضافة مالك'),
        ];
    }
}
