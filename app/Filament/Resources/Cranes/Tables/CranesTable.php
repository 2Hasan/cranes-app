<?php

namespace App\Filament\Resources\Cranes\Tables;

use App\Enums\CraneStatus;
use App\Models\Crane;
use App\Models\Site;
use App\Services\KmRecorder;
use App\Services\ServiceSchedule;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CranesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('رقم')
                    ->weight('bold')
                    ->searchable(),
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('type')
                    ->label('النوع')
                    ->searchable(),
                TextColumn::make('site.name')
                    ->label('الموقع')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge(),
                TextColumn::make('current_km')
                    ->label('العداد (كم)')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('service_due')
                    ->label('الصيانة')
                    ->state(fn (Crane $record): string => self::dueLabel($record))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'متأخرة' => 'danger',
                        'قريبة' => 'warning',
                        'مجدولة' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('site_id')
                    ->label('الموقع')
                    ->options(fn (): array => Site::query()->pluck('name', 'id')->all()),
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(CraneStatus::class),
            ])
            ->recordActions([
                Action::make('recordKm')
                    ->label('تسجيل عداد')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        TextInput::make('km')
                            ->label('قراءة العداد (كم)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(fn (Crane $record): int => $record->current_km),
                    ])
                    ->action(function (Crane $record, array $data): void {
                        app(KmRecorder::class)->record($record, (int) $data['km'], auth()->user());
                    })
                    ->successNotificationTitle('تم تسجيل القراءة')
                    ->visible(fn (): bool => auth()->user()?->can('km.record') ?? false),
                Action::make('report')
                    ->label('تقرير PDF')
                    ->icon('heroicon-o-document-text')
                    ->url(fn (Crane $record): string => route('cranes.report', $record), shouldOpenInNewTab: true)
                    ->visible(fn (): bool => auth()->user()?->can('report.export') ?? false),
                EditAction::make(),
            ]);
    }

    private static function dueLabel(Crane $record): string
    {
        return match (app(ServiceSchedule::class)->for($record)['state']) {
            ServiceSchedule::STATE_LATE => 'متأخرة',
            ServiceSchedule::STATE_SOON => 'قريبة',
            ServiceSchedule::STATE_OK => 'مجدولة',
            default => 'غير محددة',
        };
    }
}
