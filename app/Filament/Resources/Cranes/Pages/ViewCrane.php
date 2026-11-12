<?php

namespace App\Filament\Resources\Cranes\Pages;

use App\Filament\Resources\Cranes\CraneResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
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
