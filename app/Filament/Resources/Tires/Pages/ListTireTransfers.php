<?php

namespace App\Filament\Resources\Tires\Pages;

use App\Filament\Resources\Tires\TireTransferResource;
use App\Filament\Widgets\TireBalanceOverview;
use App\Models\Site;
use App\Services\TireStock;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
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
                    Select::make('site_id')
                        ->label('الموقع')
                        ->options(fn (): array => Site::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
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
                    app(TireStock::class)->addIntake(
                        Site::query()->findOrFail($data['site_id']),
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
                    Select::make('from_site_id')
                        ->label('من موقع')
                        ->options(fn (): array => Site::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
                    Select::make('to_site_id')
                        ->label('إلى موقع')
                        ->options(fn (): array => Site::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->required(),
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
                    app(TireStock::class)->transfer(
                        Site::query()->findOrFail($data['from_site_id']),
                        Site::query()->findOrFail($data['to_site_id']),
                        (int) $data['quantity'],
                        $data['handled_by'],
                        Carbon::parse($data['moved_at']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                })
                ->successNotificationTitle('تم تسجيل النقل'),
        ];
    }
}
