<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum MaintenanceType: string implements HasLabel
{
    case Periodic = 'periodic';
    case Repair = 'repair';
    case Inspection = 'inspection';
    case Parts = 'parts';

    public function getLabel(): string
    {
        return match ($this) {
            self::Periodic => 'صيانة دورية',
            self::Repair => 'إصلاح عطل',
            self::Inspection => 'فحص وسلامة',
            self::Parts => 'تغيير قطع',
        };
    }
}
