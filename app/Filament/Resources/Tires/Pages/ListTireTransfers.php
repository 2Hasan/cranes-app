<?php

namespace App\Filament\Resources\Tires\Pages;

use App\Filament\Resources\Tires\TireTransferResource;
use App\Filament\Widgets\TireBalanceOverview;
use App\Services\TireStock;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListTireTransfers extends ListRecords
{
    protected static string $resource = TireTransferResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            TireBalanceOverview::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('intake')
                ->label('إضافة مطاط لموقع')
                ->icon('heroicon-o-plus')
                ->schema([
                    self::siteNameField('site_name', 'الموقع'),
                    self::ownerNameField(),
                    TextInput::make('quantity')
                        ->label('العدد')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    DatePicker::make('received_on')
                        ->label('التاريخ')
                        ->default(now())
                        ->required(),
                    TextInput::make('note')
                        ->label('ملاحظة')
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $stock = app(TireStock::class);

                    $stock->addIntake(
                        $stock->siteByName($data['site_name']),
                        $stock->ownerByName($data['owner_name']),
                        (int) $data['quantity'],
                        Carbon::parse($data['received_on']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                })
                ->successNotificationTitle('تمت إضافة المطاط'),
            Action::make('transfer')
                ->label('نقل مطاط')
                ->icon('heroicon-o-arrow-right-circle')
                ->schema([
                    self::siteNameField('from_site_name', 'من موقع'),
                    self::ownerNameField(),
                    self::siteNameField('to_site_name', 'إلى موقع'),
                    Textarea::make('to_site_details')
                        ->label('تفاصيل الموقع الجديد (اختياري)')
                        ->maxLength(1000),
                    TextInput::make('quantity')
                        ->label('العدد')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    TextInput::make('handled_by')
                        ->label('من أخذها')
                        ->maxLength(120)
                        ->required(),
                    DateTimePicker::make('moved_at')
                        ->label('التاريخ والوقت')
                        ->default(now())
                        ->seconds(false)
                        ->required(),
                    Textarea::make('note')
                        ->label('ملاحظة')
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $stock = app(TireStock::class);

                    $stock->transfer(
                        $stock->siteByName($data['from_site_name']),
                        $stock->siteByName($data['to_site_name'], $data['to_site_details'] ?? null),
                        $stock->ownerByName($data['owner_name']),
                        (int) $data['quantity'],
                        $data['handled_by'],
                        Carbon::parse($data['moved_at']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                })
                ->successNotificationTitle('تم تسجيل النقل'),
            Action::make('damage')
                ->label('تسجيل تالف')
                ->icon('heroicon-o-exclamation-triangle')
                ->schema([
                    self::siteNameField('site_name', 'الموقع'),
                    self::ownerNameField(),
                    TextInput::make('quantity')
                        ->label('عدد التالف')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    DatePicker::make('recorded_on')
                        ->label('التاريخ')
                        ->default(now())
                        ->required(),
                    TextInput::make('note')
                        ->label('ملاحظة')
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $stock = app(TireStock::class);

                    $stock->recordDamage(
                        $stock->siteByName($data['site_name']),
                        $stock->ownerByName($data['owner_name']),
                        (int) $data['quantity'],
                        Carbon::parse($data['recorded_on']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                })
                ->successNotificationTitle('تم تسجيل التالف'),
            Action::make('returnToOwner')
                ->label('رجوع مطاط للمالك')
                ->icon('heroicon-o-arrow-uturn-left')
                ->schema([
                    self::siteNameField('site_name', 'من الموقع'),
                    self::ownerNameField(),
                    TextInput::make('quantity')
                        ->label('العدد')
                        ->numeric()
                        ->minValue(1)
                        ->required(),
                    DateTimePicker::make('returned_at')
                        ->label('التاريخ والوقت')
                        ->default(now())
                        ->seconds(false)
                        ->required(),
                    TextInput::make('note')
                        ->label('ملاحظة')
                        ->maxLength(500),
                ])
                ->action(function (array $data): void {
                    $stock = app(TireStock::class);

                    $stock->returnToOwner(
                        $stock->siteByName($data['site_name']),
                        $stock->ownerByName($data['owner_name']),
                        (int) $data['quantity'],
                        Carbon::parse($data['returned_at']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                })
                ->successNotificationTitle('تم تسجيل الرجوع للمالك'),
            Action::make('report')
                ->label('تقرير PDF')
                ->icon('heroicon-o-document-text')
                ->url(fn (): string => route('tires.report'), shouldOpenInNewTab: true),
        ];
    }

    public static function siteNameField(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->maxLength(255)
            ->required();
    }

    public static function ownerNameField(): TextInput
    {
        return TextInput::make('owner_name')
            ->label('المالك')
            ->maxLength(120)
            ->required();
    }
}
