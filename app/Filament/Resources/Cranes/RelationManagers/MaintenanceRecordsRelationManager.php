<?php

namespace App\Filament\Resources\Cranes\RelationManagers;

use App\Enums\MaintenanceType;
use App\Models\MaintenanceRecord;
use App\Services\KmRecorder;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRecords';

    protected static ?string $title = 'سجل الصيانة';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('type')
                ->label('نوع الصيانة')
                ->options(MaintenanceType::class)
                ->required(),
            DatePicker::make('performed_on')
                ->label('التاريخ')
                ->default(now())
                ->required(),
            TextInput::make('km')
                ->label('قراءة العداد (كم)')
                ->numeric()
                ->minValue(0)
                ->default(fn (): int => $this->getOwnerRecord()->current_km),
            TextInput::make('technician_name')
                ->label('الفني')
                ->maxLength(120),
            Textarea::make('description')
                ->label('ما الذي تم')
                ->required()
                ->columnSpanFull(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->label('النوع')
                    ->badge(),
                TextColumn::make('performed_on')
                    ->label('التاريخ')
                    ->date()
                    ->sortable(),
                TextColumn::make('technician_name')
                    ->label('الفني')
                    ->placeholder('—')
                    ->visibleFrom('md'),
                TextColumn::make('km')
                    ->label('العداد (كم)')
                    ->numeric()
                    ->visibleFrom('md'),
                TextColumn::make('description')
                    ->label('التفاصيل')
                    ->limit(60)
                    ->wrap(),
            ])
            ->defaultSort('performed_on', 'desc')
            ->headerActions([
                CreateAction::make()
                    ->label('تسجيل صيانة')
                    ->visible(fn (): bool => auth()->user()?->can('maintenance.create') ?? false)
                    ->mutateDataUsing(fn (array $data): array => $data + ['created_by' => auth()->id()])
                    ->after(function (MaintenanceRecord $record): void {
                        $crane = $this->getOwnerRecord();

                        // A maintenance record can also carry a newer odometer reading.
                        if ($record->km !== null && $record->km > $crane->current_km) {
                            app(KmRecorder::class)->record($crane, $record->km, auth()->user());
                        }
                    }),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => auth()->user()?->can('maintenance.create') ?? false),
            ]);
    }
}
