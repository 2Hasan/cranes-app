<?php

namespace App\Filament\Resources\Cranes\Schemas;

use App\Enums\CraneStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CraneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['default' => 1, 'xl' => 2])
            ->components([
                Section::make('البيانات الأساسية')
                    ->icon('heroicon-o-identification')
                    ->columns(['default' => 1, 'md' => 2])
                    ->components([
                        TextInput::make('code')
                            ->label('رقم الرافعة')
                            ->required()
                            ->unique(ignoreRecord: true),
                        TextInput::make('name')
                            ->label('الاسم أو الطراز'),
                        TextInput::make('type')
                            ->label('النوع')
                            ->placeholder('مثال: رافعة برجية')
                            ->maxLength(40)
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
                    ]),
                Section::make('المواصفات والعداد')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->columns(['default' => 1, 'md' => 2])
                    ->components([
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
                    ]),
                Section::make('دورة الصيانة')
                    ->description('تُستخدم لحساب موعد الصيانة القادم وتنبيه الفريق قبل موعدها.')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->columns(['default' => 1, 'md' => 2])
                    ->components([
                        TextInput::make('service_interval_days')
                            ->label('الدورة بالأيام')
                            ->numeric()
                            ->minValue(1),
                        TextInput::make('service_interval_km')
                            ->label('الدورة بالكيلومتر')
                            ->numeric()
                            ->minValue(1),
                        DatePicker::make('last_service_date')
                            ->label('تاريخ آخر صيانة دورية (للبداية فقط)'),
                        TextInput::make('last_service_km')
                            ->label('عداد آخر صيانة (للبداية فقط)')
                            ->numeric()
                            ->minValue(0),
                    ]),
                Section::make('ملاحظات')
                    ->icon('heroicon-o-document-text')
                    ->columnSpanFull()
                    ->components([
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->hiddenLabel()
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
