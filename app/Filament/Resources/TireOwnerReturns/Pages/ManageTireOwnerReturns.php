<?php

namespace App\Filament\Resources\TireOwnerReturns\Pages;

use App\Filament\Resources\TireOwnerReturns\TireOwnerReturnResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTireOwnerReturns extends ManageRecords
{
    protected static string $resource = TireOwnerReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
