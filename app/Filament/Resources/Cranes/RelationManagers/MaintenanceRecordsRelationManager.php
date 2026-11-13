<?php

namespace App\Filament\Resources\Cranes\RelationManagers;

use App\Enums\MaintenanceType;
use App\Enums\RepairResult;
use App\Models\MaintenanceRecord;
use App\Services\KmRecorder;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MaintenanceRecordsRelationManager extends RelationManager
{
    protected static string $relationship = 'maintenanceRecords';

    protected static ?string $title = 'سجل الصيانة';

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
            Select::make('type')
                ->label('نوع الصيانة')
                ->options(MaintenanceType::class)
                ->required()
                ->live(),
            Select::make('repair_result')
                ->label('نتيجة الإصلاح')
                ->options(RepairResult::class)
                ->required()
                ->visible(fn (Get $get): bool => $get('type') === MaintenanceType::Repair->value),
            DatePicker::make('performed_on')
                ->label('التاريخ')
                ->default(now())
                ->required(),
            TextInput::make('km')
                ->label('قراءة العداد (كم)')
                ->numeric()
                ->minValue(fn (): int => $this->getOwnerRecord()->current_km)
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
                Split::make([
                    Stack::make([
                        TextColumn::make('type')
                            ->label('النوع')
                            ->badge(),
                        TextColumn::make('performed_on')
                            ->label('التاريخ')
                            ->date('Y-m-d')
                            ->size('sm')
                            ->color('gray')
                            ->sortable(),
                        TextColumn::make('technician_name')
                            ->label('الفني')
                            ->placeholder('—')
                            ->size('sm')
                            ->color('gray'),
                    ]),
                    Stack::make([
                        TextColumn::make('km')
                            ->label('العداد (كم)')
                            ->numeric()
                            ->placeholder('—')
                            ->weight('bold')
                            ->alignEnd(),
                        TextColumn::make('repair_result')
                            ->label('نتيجة الإصلاح')
                            ->badge()
                            ->placeholder('—')
                            ->alignEnd(),
                    ]),
                ]),
                TextColumn::make('description')
                    ->label('التفاصيل')
                    ->size('sm')
                    ->color('gray')
                    ->limit(80)
                    ->wrap(),
            ])
            ->defaultSort('performed_on', 'desc')
            ->emptyStateHeading('لا توجد سجلات صيانة')
            ->emptyStateDescription('سجّل أول صيانة أو سيرفس لهذه الرافعة.')
            ->headerActions([
                CreateAction::make()
                    ->label('تسجيل صيانة')
                    ->modalHeading('تسجيل صيانة')
                    ->modalSubmitActionLabel('حفظ')
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
                    ->modalHeading('تعديل السجل')
                    ->modalSubmitActionLabel('حفظ')
                    ->visible(fn (): bool => auth()->user()?->can('maintenance.create') ?? false),
                DeleteAction::make()
                    ->label('حذف')
                    ->modalHeading('حذف السجل')
                    ->modalDescription('سيتم حذف هذا السجل من سجل الصيانة.')
                    ->successNotificationTitle('تم حذف السجل')
                    ->visible(fn (): bool => auth()->user()?->can('maintenance.create') ?? false),
            ]);
    }
}
