<?php

namespace App\Filament\Resources\TireReturns\Pages;

use App\Filament\Resources\TireReturns\TireReturnResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTireReturns extends ManageRecords
{
    protected static string $resource = TireReturnResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
