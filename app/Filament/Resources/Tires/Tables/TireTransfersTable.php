<?php

namespace App\Filament\Resources\Tires\Tables;

use App\Models\TireTransfer;
use App\Services\TireStock;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
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
            ->columns([
                TextColumn::make('moved_at')
                    ->label('التاريخ والوقت')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('owner.name')
                    ->label('المالك')
                    ->searchable(),
                TextColumn::make('fromSite.name')
                    ->label('من موقع')
                    ->searchable(),
                TextColumn::make('toSite.name')
                    ->label('إلى موقع')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->label('العدد')
                    ->numeric(),
                TextColumn::make('handled_by')
                    ->label('من أخذها')
                    ->searchable(),
                TextColumn::make('outstanding')
                    ->label('الحالة')
                    ->state(fn (TireTransfer $record): int => app(TireStock::class)->outstanding($record))
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "متبقي {$state}" : 'تم الاسترجاع')
                    ->badge()
                    ->color(fn (TireTransfer $record): string => match (app(TireStock::class)->status($record)) {
                        TireStock::STATUS_RETURNED => 'success',
                        TireStock::STATUS_PARTIAL => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('returns')
                    ->label('الاسترجاع')
                    ->state(fn (TireTransfer $record): string => self::returnsSummary($record))
                    ->html()
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
                        app(TireStock::class)->recordReturn(
                            $record,
                            (int) $data['returned_quantity'],
                            (int) $data['damaged_quantity'],
                            $data['returned_by'],
                            Carbon::parse($data['returned_at']),
                            auth()->user(),
                        );
                    })
                    ->successNotificationTitle('تم تسجيل الاسترجاع')
                    ->visible(fn (TireTransfer $record): bool => app(TireStock::class)->outstanding($record) > 0),
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
