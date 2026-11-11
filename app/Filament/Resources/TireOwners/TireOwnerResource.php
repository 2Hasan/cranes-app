<?php

namespace App\Filament\Resources\TireOwners;

use App\Filament\Resources\TireOwners\Pages\ManageTireOwners;
use App\Models\TireOwner;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TireOwnerResource extends Resource
{
    protected static ?string $model = TireOwner::class;

    protected static ?string $modelLabel = 'مالك مطاط';

    protected static ?string $pluralModelLabel = 'ملاك المطاط';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('اسم المالك')
                ->required()
                ->maxLength(120)
                ->unique(ignoreRecord: true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('المالك')
                    ->searchable(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTireOwners::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('tires.manage') ?? false;
    }
}
