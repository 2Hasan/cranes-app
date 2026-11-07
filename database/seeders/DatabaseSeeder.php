<?php

namespace Database\Seeders;

use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeds roles, permissions and sites. Demo users are only created locally.
     */
    public function run(): void
    {
        $this->call([
            RolePermissionSeeder::class,
            SiteSeeder::class,
        ]);

        if (! app()->environment('local')) {
            return;
        }

        $sites = Site::all();

        $demoUsers = [
            ['name' => 'مدير النظام', 'email' => 'admin@cranes.test', 'role' => 'admin', 'site' => null],
            ['name' => 'مشرف سافرة', 'email' => 'supervisor@cranes.test', 'role' => 'supervisor', 'site' => $sites->firstWhere('name', 'سافرة')],
            ['name' => 'فني المرور', 'email' => 'technician@cranes.test', 'role' => 'technician', 'site' => $sites->firstWhere('name', 'المرور')],
            ['name' => 'مشاهد', 'email' => 'viewer@cranes.test', 'role' => 'viewer', 'site' => null],
        ];

        foreach ($demoUsers as $demoUser) {
            $user = User::firstOrCreate(
                ['email' => $demoUser['email']],
                [
                    'name' => $demoUser['name'],
                    'password' => 'password',
                    'site_id' => $demoUser['site']?->getKey(),
                    'is_active' => true,
                ],
            );

            $user->syncRoles([$demoUser['role']]);
        }
    }
}
