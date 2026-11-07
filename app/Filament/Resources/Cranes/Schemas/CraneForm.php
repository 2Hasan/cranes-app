<?php

namespace App\Filament\Resources\Cranes\Schemas;

use App\Enums\CraneStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class CraneForm
{
    /** @var array<string, string> */
    public const TYPES = [
        'رافعة برجية' => 'رافعة برجية',
        'رافعة متحركة' => 'رافعة متحركة',
        'رافعة جسرية' => 'رافعة جسرية',
        'رافعة زاحفة' => 'رافعة زاحفة',
        'رافعة على شاحنة' => 'رافعة على شاحنة',
        'رافعة شوكية' => 'رافعة شوكية',
        'أخرى' => 'أخرى',
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('code')
                    ->label('رقم الرافعة')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->label('الاسم أو الطراز'),
                Select::make('type')
                    ->label('النوع')
                    ->options(self::TYPES)
                    ->searchable()
                    ->required(),
                Select::make('site_id')
                    ->label('الموقع')
                    ->relationship('site', 'name')
                    ->required(),
                Select::make('status')
                    ->label('الحالة')
                    ->options(CraneStatus::class)
                    ->default(CraneStatus::Working)
                    ->required(),
                TextInput::make('initial_km')
                    ->label('عداد الكيلومترات الحالي')
                    ->numeric()
                    ->minValue(0)
                    ->required()
                    ->visible(fn (string $operation): bool => $operation === 'create'),
                TextInput::make('capacity_ton')
                    ->label('السعة (طن)')
                    ->numeric()
                    ->minValue(0),
                TextInput::make('serial_number')
                    ->label('الرقم التسلسلي'),
                TextInput::make('manufacture_year')
                    ->label('سنة الصنع')
                    ->numeric()
                    ->minValue(1950)
                    ->maxValue(now()->year + 1),
                TextInput::make('service_interval_days')
                    ->label('دورة الصيانة بالأيام')
                    ->numeric()
                    ->minValue(1),
                TextInput::make('service_interval_km')
                    ->label('دورة الصيانة بالكيلومتر')
                    ->numeric()
                    ->minValue(1),
                DatePicker::make('last_service_date')
                    ->label('تاريخ آخر صيانة دورية (للبداية فقط)'),
                TextInput::make('last_service_km')
                    ->label('عداد آخر صيانة (للبداية فقط)')
                    ->numeric()
                    ->minValue(0),
                Textarea::make('notes')
                    ->label('ملاحظات')
                    ->columnSpanFull(),
            ]);
    }
}
