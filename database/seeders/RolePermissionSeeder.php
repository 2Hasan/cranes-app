<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles and the permissions each one grants.
     *
     * @var array<string, list<string>>
     */
    private const ROLES = [
        'admin' => [
            'crane.view', 'crane.manage', 'km.record', 'maintenance.create',
            'fault.create', 'fault.resolve', 'report.export',
            'sites.view_all', 'sites.manage', 'users.manage', 'tires.manage',
        ],
        'supervisor' => [
            'crane.view', 'crane.manage', 'km.record', 'maintenance.create',
            'fault.create', 'fault.resolve', 'report.export', 'sites.view_all', 'tires.manage',
        ],
        'technician' => [
            'crane.view', 'km.record', 'maintenance.create', 'fault.create', 'fault.resolve', 'tires.manage',
        ],
        'viewer' => [
            'crane.view', 'report.export',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $allPermissions = collect(self::ROLES)->flatten()->unique();

        foreach ($allPermissions as $permissionName) {
            Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'web']);
        }

        foreach (self::ROLES as $roleName => $permissionNames) {
            $role = Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);
            $role->syncPermissions($permissionNames);
        }
    }
}
