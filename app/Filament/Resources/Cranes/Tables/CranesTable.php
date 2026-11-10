<?php

namespace App\Filament\Resources\Cranes\Tables;

use App\Enums\CraneStatus;
use App\Models\Crane;
use App\Models\Site;
use App\Services\KmRecorder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;

class CranesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginationPageOptions([12, 24, 48])
            ->columns([
                Split::make([
                    TextColumn::make('code')
                        ->label('رقم')
                        ->html()
                        ->formatStateUsing(fn (string $state): HtmlString => new HtmlString(
                            '<img class="crane-plate-img" loading="lazy" alt="'.e('رقم الرافعة '.$state).'" src="'
                            .e(route('cranes.plate', ['code' => $state])).'">'
                        ))
                        ->searchable()
                        ->sortable(),
                    TextColumn::make('status')
                        ->label('الحالة')
                        ->badge()
                        ->alignEnd(),
                ]),
                Stack::make([
                    TextColumn::make('name')
                        ->label('الاسم')
                        ->weight('semibold')
                        ->searchable()
                        ->placeholder('بدون اسم'),
                    TextColumn::make('type')
                        ->label('النوع')
                        ->color('gray')
                        ->size('sm')
                        ->searchable(),
                ]),
                ViewColumn::make('service_due')
                    ->label('الصيانة')
                    ->view('components.service-progress'),
                Split::make([
                    TextColumn::make('site.name')
                        ->label('الموقع')
                        ->icon('heroicon-o-map-pin')
                        ->color('gray')
                        ->size('sm')
                        ->sortable(),
                    TextColumn::make('current_km')
                        ->label('العداد')
                        ->numeric()
                        ->suffix(' كم')
                        ->color('gray')
                        ->size('sm')
                        ->alignEnd()
                        ->sortable(),
                ]),
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
}
