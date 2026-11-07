<?php

namespace App\Filament\Resources\Cranes\Pages;

use App\Filament\Resources\Cranes\CraneResource;
use App\Services\KmRecorder;
use Filament\Resources\Pages\CreateRecord;

class CreateCrane extends CreateRecord
{
    protected static string $resource = CraneResource::class;

    private int $initialKm = 0;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->initialKm = (int) ($data['initial_km'] ?? 0);
        unset($data['initial_km']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->initialKm > 0) {
            app(KmRecorder::class)->record($this->getRecord(), $this->initialKm, auth()->user());
        }
    }
}
