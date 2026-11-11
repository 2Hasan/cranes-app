<?php

namespace App\Filament\Resources\Tires\Pages;

use App\Filament\Resources\Tires\TireTransferResource;
use App\Filament\Widgets\TireBalanceOverview;
use App\Models\Site;
use App\Models\TireOwner;
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
                    self::siteField('site_id', 'الموقع'),
                    self::ownerField('tire_owner_id', 'المالك'),
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
                        TireOwner::query()->findOrFail($data['tire_owner_id']),
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
                    self::siteField('from_site_id', 'من موقع'),
                    self::ownerField('tire_owner_id', 'المالك'),
                    self::siteField('to_site_id', 'إلى موقع', allowNew: true),
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
                        TireOwner::query()->findOrFail($data['tire_owner_id']),
                        (int) $data['quantity'],
                        $data['handled_by'],
                        Carbon::parse($data['moved_at']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                })
                ->successNotificationTitle('تم تسجيل النقل'),
            Action::make('returnToOwner')
                ->label('رجوع مطاط للمالك')
                ->icon('heroicon-o-arrow-uturn-left')
                ->schema([
                    self::siteField('site_id', 'من الموقع'),
                    self::ownerField('tire_owner_id', 'المالك'),
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
                    app(TireStock::class)->returnToOwner(
                        Site::query()->findOrFail($data['site_id']),
                        TireOwner::query()->findOrFail($data['tire_owner_id']),
                        (int) $data['quantity'],
                        Carbon::parse($data['returned_at']),
                        $data['note'] ?? null,
                        auth()->user(),
                    );
                })
                ->successNotificationTitle('تم تسجيل الرجوع للمالك'),
        ];
    }

    /**
     * A site picker; when $allowNew is set, a new site with its details can be created inline.
     */
    public static function siteField(string $name, string $label, bool $allowNew = false): Select
    {
        $field = Select::make($name)
            ->label($label)
            ->options(fn (): array => Site::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required();

        if ($allowNew) {
            $field
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('اسم الموقع')
                        ->required()
                        ->maxLength(255)
                        ->unique(Site::class, 'name'),
                    Textarea::make('details')
                        ->label('تفاصيل الموقع')
                        ->maxLength(1000),
                ])
                ->createOptionUsing(fn (array $data): int => Site::query()->create($data)->getKey());
        }

        return $field;
    }

    public static function ownerField(string $name, string $label): Select
    {
        return Select::make($name)
            ->label($label)
            ->options(fn (): array => TireOwner::query()->orderBy('name')->pluck('name', 'id')->all())
            ->searchable()
            ->required()
            ->createOptionForm([
                TextInput::make('name')
                    ->label('اسم المالك')
                    ->required()
                    ->maxLength(120)
                    ->unique(TireOwner::class, 'name'),
            ])
            ->createOptionUsing(fn (array $data): int => TireOwner::query()->create($data)->getKey());
    }
}
