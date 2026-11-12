<?php

namespace App\Filament\Resources\Cranes\Tables;

use App\Enums\CraneStatus;
use App\Enums\FaultStatus;
use App\Filament\Resources\Cranes\CraneResource;
use App\Filament\Resources\Cranes\RelationManagers\FaultsRelationManager;
use App\Models\Crane;
use App\Models\Site;
use App\Services\CraneAttention;
use App\Services\KmRecorder;
use App\Services\ServiceRecorder;
use App\Services\ServiceSchedule;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ViewColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\HtmlString;

class CranesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->contentGrid(['md' => 2, 'xl' => 3])
            ->paginationPageOptions([12, 24, 48])
            ->modifyQueryUsing(function (Builder $query): Builder {
                $query->withCount([
                    'faults as open_faults_count' => fn (Builder $faults) => $faults
                        ->where('status', '!=', FaultStatus::Resolved->value),
                ]);

                // Cranes needing service or with open faults come first.
                $ids = app(CraneAttention::class)->sortedIds(auth()->user());

                if ($ids === []) {
                    return $query;
                }

                $cases = collect($ids)
                    ->map(fn (int $id, int $position): string => "WHEN {$id} THEN {$position}")
                    ->implode(' ');

                return $query->orderByRaw('CASE id '.$cases.' ELSE '.count($ids).' END');
            })
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
                TextColumn::make('open_faults_count')
                    ->label('الأعطال')
                    ->state(fn (Crane $record): int => (int) $record->open_faults_count)
                    ->formatStateUsing(fn (int $state): string => $state > 0
                        ? "يوجد {$state} عطل يحتاج تصليح"
                        : 'لا توجد أعطال مفتوحة')
                    ->icon(fn (int $state): string => $state > 0
                        ? 'heroicon-o-exclamation-triangle'
                        : 'heroicon-o-check-circle')
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->weight('semibold')
                    // Always a link, so every card renders the same way and the bars below keep their width.
                    ->url(fn (Crane $record): string => CraneResource::getUrl('view', ['record' => $record]).'?relation='
                        .array_search(FaultsRelationManager::class, CraneResource::getRelations(), true)),
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
                SelectFilter::make('service')
                    ->label('الصيانة')
                    ->options(['late' => 'متأخرة', 'soon' => 'قريبة'])
                    ->query(function (Builder $query, array $data): Builder {
                        $states = ['late' => ServiceSchedule::STATE_LATE, 'soon' => ServiceSchedule::STATE_SOON];

                        if (! isset($states[$data['value'] ?? null])) {
                            return $query;
                        }

                        return $query->whereIn('id', app(CraneAttention::class)
                            ->idsWithServiceState(auth()->user(), $states[$data['value']]));
                    }),
                SelectFilter::make('faults')
                    ->label('الأعطال')
                    ->options(['open' => 'فيها أعطال غير مصلحة'])
                    ->query(function (Builder $query, array $data): Builder {
                        if (($data['value'] ?? null) !== 'open') {
                            return $query;
                        }

                        return $query->whereIn('id', app(CraneAttention::class)->idsWithOpenFaults(auth()->user()));
                    }),
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
                Action::make('recordService')
                    ->label('تسجيل سيرفس')
                    ->icon('heroicon-o-wrench')
                    ->schema([
                        DatePicker::make('performed_on')
                            ->label('تاريخ السيرفس')
                            ->default(now())
                            ->required(),
                        TextInput::make('km')
                            ->label('العداد عند السيرفس (كم)')
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->default(fn (Crane $record): int => $record->current_km),
                    ])
                    ->action(function (Crane $record, array $data): void {
                        app(ServiceRecorder::class)->record(
                            $record,
                            Carbon::parse($data['performed_on']),
                            (int) $data['km'],
                            auth()->user(),
                        );
                    })
                    ->successNotificationTitle('تم تسجيل السيرفس')
                    ->visible(fn (): bool => auth()->user()?->can('maintenance.create') ?? false),
                ActionGroup::make([
                    Action::make('report')
                        ->label('تقرير PDF')
                        ->icon('heroicon-o-document-text')
                        ->url(fn (Crane $record): string => route('cranes.report', $record), shouldOpenInNewTab: true)
                        ->visible(fn (): bool => auth()->user()?->can('report.export') ?? false),
                    EditAction::make(),
                ])
                    ->label('المزيد')
                    ->icon('heroicon-o-ellipsis-vertical')
                    ->color('gray')
                    ->size('sm'),
            ]);
    }
}
