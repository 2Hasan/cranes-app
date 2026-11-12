<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\User;
use App\Services\InvitationLink;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'المستخدمون';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    /** @var array<string, string> */
    public const ROLE_LABELS = [
        'admin' => 'مدير النظام',
        'supervisor' => 'مشرف',
        'technician' => 'فني',
        'viewer' => 'مشاهد',
    ];

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('الاسم')
                ->required(),
            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),
            TextInput::make('phone')
                ->label('الجوال')
                ->tel(),
            Select::make('site_id')
                ->label('الموقع')
                ->relationship('site', 'name')
                ->searchable(),
            Select::make('role')
                ->label('الدور')
                ->options(fn (): array => Role::query()
                    ->orderBy('name')
                    ->pluck('name')
                    ->mapWithKeys(fn (string $name): array => [$name => self::ROLE_LABELS[$name] ?? $name])
                    ->all())
                ->required(),
            Toggle::make('is_active')
                ->label('الحساب فعّال')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('البريد')
                    ->searchable()
                    ->visibleFrom('md'),
                TextColumn::make('roles.name')
                    ->label('الدور')
                    ->formatStateUsing(fn (string $state): string => self::ROLE_LABELS[$state] ?? $state)
                    ->badge(),
                TextColumn::make('site.name')
                    ->label('الموقع')
                    ->visibleFrom('md'),
                IconColumn::make('is_active')
                    ->label('فعّال')
                    ->boolean(),
            ])
            ->recordActions([
                Action::make('newInviteLink')
                    ->label('رابط دعوة جديد')
                    ->icon('heroicon-o-link')
                    ->iconButton()
                    ->action(function (User $record, $livewire): void {
                        $invitation = app(InvitationLink::class);
                        $link = $invitation->for($record);

                        $livewire->js($invitation->copyScript($link));

                        Notification::make()
                            ->title('رابط الدعوة (تم نسخه)')
                            ->body('أرسل هذا الرابط للمستخدم. ينتهي خلال 48 ساعة:'."\n".$link)
                            ->success()
                            ->persistent()
                            ->send();
                    }),
                EditAction::make()->iconButton(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }
}
