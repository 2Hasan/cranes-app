<?php

namespace Tests\Feature;

use App\Enums\MaintenanceType;
use App\Filament\Resources\Cranes\Pages\ViewCrane;
use App\Filament\Resources\Cranes\RelationManagers\MaintenanceRecordsRelationManager;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MaintenanceTechnicianNameTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_maintenance_record_can_be_saved_without_a_technician(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $crane = Crane::factory()->create(['site_id' => Site::factory()->create()->getKey()]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRecordsRelationManager::class, [
                'ownerRecord' => $crane,
                'pageClass' => ViewCrane::class,
            ])
            ->callTableAction('create', data: [
                'type' => MaintenanceType::Periodic->value,
                'performed_on' => now()->toDateString(),
                'km' => 1200,
                'technician_name' => '',
                'description' => 'تغيير زيت',
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('maintenance_records', [
            'crane_id' => $crane->getKey(),
            'technician_name' => null,
            'description' => 'تغيير زيت',
        ]);
    }

    public function test_a_technician_name_is_typed_and_saved_as_text(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $crane = Crane::factory()->create(['site_id' => Site::factory()->create()->getKey()]);

        Livewire::actingAs($admin)
            ->test(MaintenanceRecordsRelationManager::class, [
                'ownerRecord' => $crane,
                'pageClass' => ViewCrane::class,
            ])
            ->callTableAction('create', data: [
                'type' => MaintenanceType::Repair->value,
                'performed_on' => now()->toDateString(),
                'km' => null,
                'technician_name' => 'أحمد السيد',
                'description' => 'تبديل كابل',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame('أحمد السيد', MaintenanceRecord::query()->where('crane_id', $crane->getKey())->value('technician_name'));
    }
}
