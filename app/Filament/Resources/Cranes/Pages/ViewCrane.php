<?php

namespace App\Filament\Resources\Cranes\Pages;

use App\Enums\CraneStatus;
use App\Filament\Resources\Cranes\CraneResource;
use App\Models\Crane;
use App\Models\Site;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ViewCrane extends ViewRecord
{
    protected static string $resource = CraneResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('البيانات الأساسية')
                ->icon('heroicon-o-identification')
                ->headerActions([
                    // Quick changes for the two fields that change most often.
                    Action::make('quickStatus')
                        ->label('تغيير الحالة')
                        ->icon('heroicon-o-arrow-path')
                        ->modalHeading('تغيير حالة الرافعة')
                        ->schema([
                            Select::make('status')
                                ->label('الحالة')
                                ->options(CraneStatus::class)
                                ->required(),
                        ])
                        ->fillForm(fn (Crane $record): array => ['status' => $record->status])
                        ->action(fn (Crane $record, array $data) => $record->update(['status' => $data['status']]))
                        ->successNotificationTitle('تم تغيير الحالة')
                        ->visible(fn (): bool => auth()->user()?->can('crane.manage') ?? false),
                    Action::make('quickSite')
                        ->label('تغيير الموقع')
                        ->icon('heroicon-o-map-pin')
                        ->modalHeading('تغيير موقع الرافعة')
                        ->schema([
                            Select::make('site_id')
                                ->label('الموقع')
                                ->options(fn (): array => Site::query()->pluck('name', 'id')->all())
                                ->required(),
                        ])
                        ->fillForm(fn (Crane $record): array => ['site_id' => $record->site_id])
                        ->action(fn (Crane $record, array $data) => $record->update(['site_id' => $data['site_id']]))
                        ->successNotificationTitle('تم تغيير الموقع')
                        ->visible(fn (): bool => auth()->user()?->can('crane.manage') ?? false),
                    // Editing happens in place, in a modal, at the section's top-left corner.
                    CraneResource::editAction(),
                ])
                ->columns(['default' => 2, 'md' => 3])
                ->components([
                    TextEntry::make('code')
                        ->label('رقم الرافعة'),
                    TextEntry::make('name')
                        ->label('الاسم أو الطراز')
                        ->placeholder('—'),
                    TextEntry::make('type')
                        ->label('النوع'),
                    TextEntry::make('site.name')
                        ->label('الموقع'),
                    TextEntry::make('status')
                        ->label('الحالة')
                        ->badge(),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('report')
                ->label('تقرير PDF')
                ->icon('heroicon-o-document-text')
                ->color('gray')
                ->url(fn (): string => route('cranes.report', $this->getRecord()), shouldOpenInNewTab: true)
                ->visible(fn (): bool => auth()->user()?->can('report.export') ?? false),
            DeleteAction::make()
                ->visible(fn (): bool => auth()->user()?->can('crane.manage') ?? false),
        ];
    }
}
