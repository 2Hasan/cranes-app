<?php

namespace App\Filament\Resources\Cranes\RelationManagers;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\Fault;
use App\Models\User;
use App\Services\MaintenanceAlerts;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FaultsRelationManager extends RelationManager
{
    protected static string $relationship = 'faults';

    protected static ?string $title = 'الأعطال';

    /**
     * The crane's view page is read-only by default, which would hide these actions.
     * Each action checks its own permission instead.
     */
    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('العطل')
                ->required()
                ->columnSpanFull(),
            Select::make('severity')
                ->label('الخطورة')
                ->options(FaultSeverity::class)
                ->default(FaultSeverity::Medium)
                ->required(),
            Select::make('assigned_to')
                ->label('المسؤول عن الإصلاح')
                ->options(fn (): array => User::role(['technician', 'supervisor'])->pluck('name', 'id')->all())
                ->searchable(),
            Textarea::make('description')
                ->label('التفاصيل')
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([
                    Stack::make([
                        TextColumn::make('title')
                            ->label('العطل')
                            ->weight('bold')
                            ->wrap(),
                        TextColumn::make('reported_at')
                            ->label('تاريخ البلاغ')
                            ->dateTime('Y-m-d H:i')
                            ->size('sm')
                            ->color('gray')
                            ->sortable(),
                        TextColumn::make('assignee.name')
                            ->label('المسؤول')
                            ->placeholder('—')
                            ->size('sm')
                            ->color('gray'),
                    ]),
                    Stack::make([
                        TextColumn::make('severity')
                            ->label('الخطورة')
                            ->badge()
                            ->alignEnd(),
                        TextColumn::make('status')
                            ->label('الحالة')
                            ->badge()
                            ->alignEnd(),
                    ]),
                ]),
            ])
            ->defaultSort('reported_at', 'desc')
            ->emptyStateHeading('لا توجد أعطال مسجلة')
            ->emptyStateDescription('لم يتم تسجيل أي عطل لهذه الرافعة.')
            ->headerActions([
                CreateAction::make()
                    ->label('تسجيل عطل')
                    ->visible(fn (): bool => auth()->user()?->can('fault.create') ?? false)
                    ->mutateDataUsing(fn (array $data): array => $data + [
                        'status' => FaultStatus::Open,
                        'reported_by' => auth()->id(),
                        'reported_at' => now(),
                    ])
                    ->after(function (Fault $record): void {
                        if (in_array($record->severity, [FaultSeverity::High, FaultSeverity::Critical], true)) {
                            app(MaintenanceAlerts::class)->notifySevereFault($record);
                        }
                    }),
            ])
            ->recordActions([
                Action::make('startRepair')
                    ->label('بدء الإصلاح')
                    ->color('warning')
                    ->visible(fn (Fault $record): bool => $record->status === FaultStatus::Open
                        && (auth()->user()?->can('fault.resolve') ?? false))
                    ->action(fn (Fault $record): bool => $record->update(['status' => FaultStatus::InProgress])),
                Action::make('closeFault')
                    ->label('إغلاق العطل')
                    ->color('success')
                    ->visible(fn (Fault $record): bool => $record->status !== FaultStatus::Resolved
                        && (auth()->user()?->can('fault.resolve') ?? false))
                    ->schema([
                        Select::make('maintenance_record_id')
                            ->label('سجل الصيانة الذي أصلحه')
                            ->options(fn (): array => $this->getOwnerRecord()
                                ->maintenanceRecords()
                                ->latest('performed_on')
                                ->limit(50)
                                ->get()
                                ->mapWithKeys(fn ($record): array => [
                                    $record->id => $record->performed_on->format('Y-m-d').' — '.$record->type->getLabel(),
                                ])
                                ->all())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (Fault $record, array $data): void {
                        $record->update([
                            'status' => FaultStatus::Resolved,
                            'resolved_at' => now(),
                            'maintenance_record_id' => $data['maintenance_record_id'],
                        ]);
                    })
                    ->successNotificationTitle('تم إغلاق العطل'),
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('fault.create') ?? false),
            ]);
    }
}
