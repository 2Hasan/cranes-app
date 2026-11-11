<?php

namespace App\Filament\Resources\TireIntakes\Pages;

use App\Filament\Resources\TireIntakes\TireIntakeResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTireIntakes extends ManageRecords
{
    protected static string $resource = TireIntakeResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
