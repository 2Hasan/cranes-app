<?php

namespace App\Filament\Resources\TireOwnerReturns;

use App\Filament\Resources\TireOwnerReturns\Pages\ManageTireOwnerReturns;
use App\Filament\Resources\Tires\Pages\ListTireTransfers;
use App\Models\TireOwnerReturn;
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

class TireOwnerReturnResource extends Resource
{
    protected static ?string $model = TireOwnerReturn::class;

    protected static ?string $modelLabel = 'رجوع للمالك';

    protected static ?string $pluralModelLabel = 'الرجوع للمالك';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    public static function table(Table $table): Table
    {
        return $table
            ->query(TireOwnerReturn::query()->with(['site', 'owner']))
            ->defaultSort('returned_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                TextColumn::make('returned_at')
                    ->label('التاريخ والوقت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('site.name')
                    ->label('من الموقع')
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
                    ->modalHeading('تعديل الرجوع للمالك')
                    ->schema([
                        ListTireTransfers::siteNameField('site_name', 'من الموقع'),
                        ListTireTransfers::ownerNameField(),
                        TextInput::make('quantity')
                            ->label('العدد')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        DateTimePicker::make('returned_at')
                            ->label('التاريخ والوقت')
                            ->seconds(false)
                            ->required(),
                        TextInput::make('note')
                            ->label('ملاحظة')
                            ->maxLength(500),
                    ])
                    ->fillForm(fn (TireOwnerReturn $record): array => [
                        'site_name' => $record->site->name,
                        'owner_name' => $record->owner->name,
                        'quantity' => $record->quantity,
                        'returned_at' => $record->returned_at,
                        'note' => $record->note,
                    ])
                    ->action(function (TireOwnerReturn $record, array $data): void {
                        ListTireTransfers::runStockChange(function () use ($record, $data): void {
                            $stock = app(TireStock::class);

                            $stock->updateOwnerReturn(
                                $record,
                                $stock->siteByName($data['site_name']),
                                $stock->ownerByName($data['owner_name']),
                                (int) $data['quantity'],
                                Carbon::parse($data['returned_at']),
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
                    ->modalDescription('سيتم حذف هذا الرجوع للمالك نهائياً، ولا يمكن التراجع.')
                    ->action(fn (TireOwnerReturn $record) => ListTireTransfers::runStockChange(
                        fn () => app(TireStock::class)->deleteOwnerReturn($record),
                    ))
                    ->successNotificationTitle('تم الحذف'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTireOwnerReturns::route('/'),
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
