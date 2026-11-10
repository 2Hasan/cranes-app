<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RepairResult: string implements HasLabel
{
    case Fixed = 'fixed';
    case NotFixed = 'not_fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'تم الإصلاح',
            self::NotFixed => 'لم يتم الإصلاح',
        };
    }
}
