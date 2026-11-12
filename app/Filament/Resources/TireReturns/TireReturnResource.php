<?php

namespace App\Filament\Resources\TireReturns;

use App\Filament\Resources\TireReturns\Pages\ManageTireReturns;
use App\Filament\Resources\Tires\Pages\ListTireTransfers;
use App\Models\TireReturn;
use App\Services\TireStock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TireReturnResource extends Resource
{
    protected static ?string $model = TireReturn::class;

    protected static ?string $modelLabel = 'استرجاع';

    protected static ?string $pluralModelLabel = 'الاسترجاعات';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowPath;

    public static function table(Table $table): Table
    {
        return $table
            ->query(TireReturn::query()->with(['transfer.fromSite', 'transfer.toSite', 'transfer.owner']))
            ->defaultSort('returned_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('returned_at')
                    ->label('التاريخ والوقت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('transfer.toSite.name')
                    ->label('من موقع النقل')
                    ->weight('semibold')
                    ->searchable(),
                TextColumn::make('transfer.fromSite.name')
                    ->label('إلى الموقع الأصلي')
                    ->color('gray'),
                TextColumn::make('transfer.owner.name')
                    ->label('المالك')
                    ->color('gray'),
                TextColumn::make('returned_quantity')
                    ->label('صالح')
                    ->numeric(),
                TextColumn::make('damaged_quantity')
                    ->label('تالف')
                    ->numeric()
                    ->color('danger'),
                TextColumn::make('returned_by')
                    ->label('من رجعها')
                    ->visibleFrom('md'),
            ])
            ->recordActions([
                Action::make('edit')
                    ->label('تعديل')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('تعديل الاسترجاع')
                    ->schema([
                        TextInput::make('returned_quantity')
                            ->label('العدد الصالح الراجع')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('damaged_quantity')
                            ->label('العدد التالف')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        TextInput::make('returned_by')
                            ->label('من رجعها')
                            ->maxLength(120)
                            ->required(),
                        DateTimePicker::make('returned_at')
                            ->label('التاريخ والوقت')
                            ->seconds(false)
                            ->required(),
                    ])
                    ->fillForm(fn (TireReturn $record): array => [
                        'returned_quantity' => $record->returned_quantity,
                        'damaged_quantity' => $record->damaged_quantity,
                        'returned_by' => $record->returned_by,
                        'returned_at' => $record->returned_at,
                    ])
                    ->action(function (TireReturn $record, array $data): void {
                        ListTireTransfers::runStockChange(fn () => app(TireStock::class)->updateReturn(
                            $record,
                            (int) $data['returned_quantity'],
                            (int) $data['damaged_quantity'],
                            $data['returned_by'],
                            Carbon::parse($data['returned_at']),
                        ));
                    })
                    ->successNotificationTitle('تم التعديل'),
                Action::make('delete')
                    ->label('حذف نهائي')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('سيتم حذف هذا الاسترجاع نهائياً، ولا يمكن التراجع.')
                    ->action(fn (TireReturn $record) => ListTireTransfers::runStockChange(
                        fn () => app(TireStock::class)->deleteReturn($record),
                    ))
                    ->successNotificationTitle('تم الحذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTireReturns::route('/'),
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
