<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum CraneStatus: string implements HasLabel, HasColor
{
    case Working = 'working';
    case UnderMaintenance = 'maintenance';
    case Stopped = 'stopped';

    public function getLabel(): string
    {
        return match ($this) {
            self::Working => 'تعمل',
            self::UnderMaintenance => 'تحت الصيانة',
            self::Stopped => 'متوقفة',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Working => 'success',
            self::UnderMaintenance => 'warning',
            self::Stopped => 'danger',
        };
    }
}
