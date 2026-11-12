<?php

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Spatie\Permission\Models\Role;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static ?string $modelLabel = 'دور';

    protected static ?string $pluralModelLabel = 'الأدوار والصلاحيات';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    /** Roles the system relies on. They can be edited but not deleted. */
    public const SYSTEM_ROLES = ['admin', 'supervisor', 'technician', 'viewer'];

    /** @var array<string, string> */
    public const PERMISSION_LABELS = [
        'crane.view' => 'عرض الرافعات',
        'crane.manage' => 'إضافة الرافعات وتعديلها',
        'km.record' => 'تسجيل العداد',
        'maintenance.create' => 'تسجيل الصيانة والسيرفس',
        'fault.create' => 'تسجيل الأعطال',
        'fault.resolve' => 'بدء الإصلاح وإغلاق الأعطال',
        'report.export' => 'تصدير التقارير PDF',
        'sites.view_all' => 'رؤية كل المواقع',
        'sites.manage' => 'إدارة المواقع',
        'users.manage' => 'إدارة المستخدمين والأدوار',
        'tires.manage' => 'إدارة المطاط',
    ];

    public static function table(Table $table): Table
    {
        return $table
            ->query(Role::query()->withCount(['permissions', 'users']))
            ->columns([
                Stack::make([
                    TextColumn::make('name')
                        ->label('الدور')
                        ->weight('semibold')
                        ->searchable(),
                    TextColumn::make('permissions_count')
                        ->label('عدد الصلاحيات')
                        ->suffix(' صلاحية')
                        ->size('sm')
                        ->color('gray'),
                    TextColumn::make('users_count')
                        ->label('عدد المستخدمين')
                        ->suffix(' مستخدم')
                        ->size('sm')
                        ->color('gray'),
                ]),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->label('تعديل')
                    ->modalHeading('تعديل الدور')
                    ->schema(self::formSchema())
                    ->fillForm(fn (Role $record): array => [
                        'name' => $record->name,
                        'permissions' => $record->permissions->pluck('name')->all(),
                    ])
                    ->action(fn (Role $record, array $data) => self::savePermissions($record, $data)),
                Action::make('delete')
                    ->label('حذف')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (Role $record): bool => ! in_array($record->name, self::SYSTEM_ROLES, true))
                    ->requiresConfirmation()
                    ->modalDescription('سيتم حذف الدور، والمستخدمون عليه يحتاجون دوراً آخر.')
                    ->action(fn (Role $record) => $record->delete()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('دور جديد')
                    ->modalHeading('دور جديد')
                    ->schema(self::formSchema())
                    ->action(function (array $data): void {
                        $role = Role::create(['name' => $data['name'], 'guard_name' => 'web']);

                        self::savePermissions($role, $data);
                    }),
            ]);
    }

    /** @return array<int, Component> */
    public static function formSchema(): array
    {
        return [
            TextInput::make('name')
                ->label('اسم الدور')
                ->required()
                ->maxLength(60)
                ->unique(table: Role::class, column: 'name', ignoreRecord: true),
            CheckboxList::make('permissions')
                ->label('الصلاحيات')
                ->options(fn (): array => collect(self::PERMISSION_LABELS)->all())
                ->columns(['default' => 1, 'md' => 2])
                ->bulkToggleable(),
        ];
    }

    public static function savePermissions(Role $role, array $data): void
    {
        if (isset($data['name']) && $role->name !== $data['name'] && ! in_array($role->name, self::SYSTEM_ROLES, true)) {
            $role->update(['name' => $data['name']]);
        }

        $role->syncPermissions($data['permissions'] ?? []);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('users.manage') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
