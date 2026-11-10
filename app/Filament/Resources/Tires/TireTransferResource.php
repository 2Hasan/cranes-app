<?php

namespace App\Filament\Resources\Tires;

use App\Filament\Resources\Tires\Pages\ListTireTransfers;
use App\Filament\Resources\Tires\Tables\TireTransfersTable;
use App\Models\TireTransfer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class TireTransferResource extends Resource
{
    protected static ?string $model = TireTransfer::class;

    protected static ?string $modelLabel = 'نقل مطاط';

    protected static ?string $pluralModelLabel = 'المطاط';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCircleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return TireTransfersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTireTransfers::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('tires.manage') ?? false;
    }
}
