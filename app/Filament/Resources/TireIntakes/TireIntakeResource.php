<?php

namespace App\Filament\Resources\TireIntakes;

use App\Filament\Resources\TireIntakes\Pages\ManageTireIntakes;
use App\Filament\Resources\Tires\Pages\ListTireTransfers;
use App\Models\TireIntake;
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

class TireIntakeResource extends Resource
{
    protected static ?string $model = TireIntake::class;

    protected static ?string $modelLabel = 'إضافة مطاط';

    protected static ?string $pluralModelLabel = 'إضافات المطاط';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    public static function table(Table $table): Table
    {
        return $table
            ->query(TireIntake::query()->with(['site', 'owner']))
            ->defaultSort('received_on', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('received_on')
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
                    ->suffix(' مطاط'),
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
                    ->modalHeading('تعديل الإضافة')
                    ->schema([
                        ListTireTransfers::siteNameField('site_name', 'الموقع'),
                        ListTireTransfers::ownerNameField(),
                        TextInput::make('quantity')
                            ->label('العدد')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        DatePicker::make('received_on')
                            ->label('التاريخ')
                            ->required(),
                        TextInput::make('note')
                            ->label('ملاحظة')
                            ->maxLength(500),
                    ])
                    ->fillForm(fn (TireIntake $record): array => [
                        'site_name' => $record->site->name,
                        'owner_name' => $record->owner->name,
                        'quantity' => $record->quantity,
                        'received_on' => $record->received_on,
                        'note' => $record->note,
                    ])
                    ->action(function (TireIntake $record, array $data): void {
                        ListTireTransfers::runStockChange(function () use ($record, $data): void {
                            $stock = app(TireStock::class);

                            $stock->updateIntake(
                                $record,
                                $stock->siteByName($data['site_name']),
                                $stock->ownerByName($data['owner_name']),
                                (int) $data['quantity'],
                                Carbon::parse($data['received_on']),
                                $data['note'] ?? null,
                            );
                        });
                    })
                    ->successNotificationTitle('تم التعديل'),
                Action::make('delete')
                    ->label('حذف نهائي')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('سيتم حذف هذه الإضافة نهائياً، ولا يمكن التراجع.')
                    ->action(fn (TireIntake $record) => ListTireTransfers::runStockChange(
                        fn () => app(TireStock::class)->deleteIntake($record),
                    ))
                    ->successNotificationTitle('تم الحذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTireIntakes::route('/'),
        ];
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
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
