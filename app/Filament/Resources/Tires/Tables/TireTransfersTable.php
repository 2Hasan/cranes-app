<?php

namespace App\Filament\Resources\Tires\Tables;

use App\Filament\Resources\Tires\Pages\ListTireTransfers;
use App\Models\TireTransfer;
use App\Services\TireStock;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;

class TireTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(TireTransfer::query()->with(['fromSite', 'toSite', 'owner', 'returns']))
            ->defaultSort('moved_at', 'desc')
            ->paginationPageOptions([10, 25, 50])
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('fromSite.name')
                            ->label('من موقع')
                            ->weight('semibold')
                            ->searchable(),
                        TextColumn::make('toSite.name')
                            ->label('إلى موقع')
                            ->icon('heroicon-o-arrow-long-left')
                            ->color('gray')
                            ->searchable(),
                        TextColumn::make('owner.name')
                            ->label('المالك')
                            ->size('sm')
                            ->color('gray')
                            ->searchable(),
                        TextColumn::make('handled_by')
                            ->label('من أخذها')
                            ->size('sm')
                            ->color('gray')
                            ->searchable(),
                        TextColumn::make('moved_at')
                            ->label('التاريخ والوقت')
                            ->dateTime('Y-m-d H:i')
                            ->size('sm')
                            ->color('gray')
                            ->sortable(),
                    ]),
                    Stack::make([
                        TextColumn::make('quantity')
                            ->label('العدد')
                            ->numeric()
                            ->suffix(' مطاط')
                            ->weight('bold')
                            ->alignEnd(),
                        TextColumn::make('outstanding')
                            ->label('الحالة')
                            ->state(fn (TireTransfer $record): int => app(TireStock::class)->outstanding($record))
                            ->formatStateUsing(fn (int $state): string => $state > 0 ? "متبقي {$state}" : 'تم الاسترجاع')
                            ->badge()
                            ->alignEnd()
                            ->color(fn (TireTransfer $record): string => match (app(TireStock::class)->status($record)) {
                                TireStock::STATUS_RETURNED => 'success',
                                TireStock::STATUS_PARTIAL => 'warning',
                                default => 'danger',
                            }),
                    ])->alignEnd(),
                ]),
                TextColumn::make('returns')
                    ->label('الاسترجاع')
                    ->state(fn (TireTransfer $record): string => self::returnsSummary($record))
                    ->html()
                    ->size('sm')
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('recordReturn')
                    ->label('تسجيل استرجاع')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->schema([
                        TextInput::make('returned_quantity')
                            ->label('العدد الصالح الراجع')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('damaged_quantity')
                            ->label('العدد التالف')
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->required(),
                        TextInput::make('returned_by')
                            ->label('من رجعها')
                            ->maxLength(120)
                            ->required(),
                        DateTimePicker::make('returned_at')
                            ->label('التاريخ والوقت')
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                    ])
                    ->action(function (TireTransfer $record, array $data): void {
                        ListTireTransfers::runStockChange(fn () => app(TireStock::class)->recordReturn(
                            $record,
                            (int) $data['returned_quantity'],
                            (int) $data['damaged_quantity'],
                            $data['returned_by'],
                            Carbon::parse($data['returned_at']),
                            auth()->user(),
                        ));
                    })
                    ->successNotificationTitle('تم تسجيل الاسترجاع')
                    ->visible(fn (TireTransfer $record): bool => app(TireStock::class)->outstanding($record) > 0),
                Action::make('editTransfer')
                    ->label('تعديل')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('تعديل النقل')
                    ->schema([
                        ListTireTransfers::siteNameField('from_site_name', 'من موقع'),
                        ListTireTransfers::ownerNameField(),
                        ListTireTransfers::siteNameField('to_site_name', 'إلى موقع'),
                        Textarea::make('to_site_details')
                            ->label('تفاصيل الموقع (اختياري)')
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
                            ->seconds(false)
                            ->required(),
                        Textarea::make('note')
                            ->label('ملاحظة')
                            ->maxLength(500),
                    ])
                    ->fillForm(fn (TireTransfer $record): array => [
                        'from_site_name' => $record->fromSite->name,
                        'owner_name' => $record->owner->name,
                        'to_site_name' => $record->toSite->name,
                        'to_site_details' => $record->toSite->details,
                        'quantity' => $record->quantity,
                        'handled_by' => $record->handled_by,
                        'moved_at' => $record->moved_at,
                        'note' => $record->note,
                    ])
                    ->action(function (TireTransfer $record, array $data): void {
                        ListTireTransfers::runStockChange(function () use ($record, $data): void {
                            $stock = app(TireStock::class);

                            $stock->updateTransfer(
                                $record,
                                $stock->siteByName($data['from_site_name']),
                                $stock->siteByName($data['to_site_name'], $data['to_site_details'] ?? null),
                                $stock->ownerByName($data['owner_name']),
                                (int) $data['quantity'],
                                $data['handled_by'],
                                Carbon::parse($data['moved_at']),
                                $data['note'] ?? null,
                            );
                        });
                    })
                    ->successNotificationTitle('تم تعديل النقل'),
                Action::make('deleteTransfer')
                    ->label('حذف نهائي')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('سيتم حذف النقل وكل الاسترجاعات المرتبطة به نهائياً، ولا يمكن التراجع.')
                    ->action(fn (TireTransfer $record) => ListTireTransfers::runStockChange(
                        fn () => app(TireStock::class)->deleteTransfer($record),
                    ))
                    ->successNotificationTitle('تم حذف النقل'),
            ]);
    }

    private static function returnsSummary(TireTransfer $transfer): string
    {
        if ($transfer->returns->isEmpty()) {
            return '—';
        }

        return $transfer->returns
            ->sortBy('returned_at')
            ->map(fn ($return): string => e(sprintf(
                '%s · %s · صالح %d · تالف %d',
                $return->returned_at->format('Y-m-d H:i'),
                $return->returned_by,
                $return->returned_quantity,
                $return->damaged_quantity,
            )))
            ->implode('<br>');
    }
}
