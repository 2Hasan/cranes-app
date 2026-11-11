<?php

namespace App\Filament\Resources\TireDamages;

use App\Filament\Resources\TireDamages\Pages\ManageTireDamages;
use App\Filament\Resources\Tires\Pages\ListTireTransfers;
use App\Models\TireDamage;
use App\Services\TireStock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TireDamageResource extends Resource
{
    protected static ?string $model = TireDamage::class;

    protected static ?string $modelLabel = 'تالف';

    protected static ?string $pluralModelLabel = 'التالف';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    public static function table(Table $table): Table
    {
        return $table
            ->query(TireDamage::query()->with(['site', 'owner']))
            ->defaultSort('recorded_on', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('recorded_on')
                    ->label('التاريخ')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('site.name')
                    ->label('الموقع')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('owner.name')
                    ->label('المالك')
                    ->color('gray')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('العدد')
                    ->numeric()
                    ->suffix(' تالف'),
                TextColumn::make('note')
                    ->label('ملاحظة')
                    ->placeholder('—')
                    ->limit(40)
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('تعديل')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('تعديل التالف')
                    ->schema([
                        ListTireTransfers::siteNameField('site_name', 'الموقع'),
                        ListTireTransfers::ownerNameField(),
                        TextInput::make('quantity')
                            ->label('عدد التالف')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        DatePicker::make('recorded_on')
                            ->label('التاريخ')
                            ->required(),
                        TextInput::make('note')
                            ->label('ملاحظة')
                            ->maxLength(500),
                    ])
                    ->fillForm(fn (TireDamage $record): array => [
                        'site_name' => $record->site->name,
                        'owner_name' => $record->owner->name,
                        'quantity' => $record->quantity,
                        'recorded_on' => $record->recorded_on,
                        'note' => $record->note,
                    ])
                    ->action(function (TireDamage $record, array $data): void {
                        $stock = app(TireStock::class);

                        $stock->updateDamage(
                            $record,
                            $stock->siteByName($data['site_name']),
                            $stock->ownerByName($data['owner_name']),
                            (int) $data['quantity'],
                            Carbon::parse($data['recorded_on']),
                            $data['note'] ?? null,
                        );
                    })
                    ->successNotificationTitle('تم التعديل'),
                Action::make('delete')
                    ->label('حذف نهائي')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('سيتم حذف هذا التالف نهائياً، ولا يمكن التراجع.')
                    ->action(fn (TireDamage $record) => app(TireStock::class)->deleteDamage($record))
                    ->successNotificationTitle('تم الحذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTireDamages::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('tires.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
