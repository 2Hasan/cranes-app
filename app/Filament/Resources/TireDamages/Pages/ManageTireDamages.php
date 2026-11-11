<?php

namespace App\Filament\Resources\TireDamages\Pages;

use App\Filament\Resources\TireDamages\TireDamageResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTireDamages extends ManageRecords
{
    protected static string $resource = TireDamageResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
