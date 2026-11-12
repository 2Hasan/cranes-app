<?php

namespace App\Filament\Resources\Cranes;

use App\Filament\Resources\Cranes\Pages\CreateCrane;
use App\Filament\Resources\Cranes\Pages\EditCrane;
use App\Filament\Resources\Cranes\Pages\ListCranes;
use App\Filament\Resources\Cranes\Pages\ViewCrane;
use App\Filament\Resources\Cranes\RelationManagers\FaultsRelationManager;
use App\Filament\Resources\Cranes\RelationManagers\MaintenanceRecordsRelationManager;
use App\Filament\Resources\Cranes\Schemas\CraneForm;
use App\Filament\Resources\Cranes\Tables\CranesTable;
use App\Models\Crane;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CraneResource extends Resource
{
    protected static ?string $model = Crane::class;

    protected static ?string $modelLabel = 'رافعة';

    protected static ?string $pluralModelLabel = 'الرافعات';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return CraneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CranesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            MaintenanceRecordsRelationManager::class,
            FaultsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCranes::route('/'),
            'create' => CreateCrane::route('/create'),
            'view' => ViewCrane::route('/{record}'),
            'edit' => EditCrane::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->visibleTo(auth()->user());
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('crane.view') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can('crane.manage') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->can('crane.manage') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->can('crane.manage') ?? false;
    }
}
