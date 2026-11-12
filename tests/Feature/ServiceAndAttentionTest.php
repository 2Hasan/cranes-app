<?php

namespace Tests\Feature;

use App\Enums\MaintenanceType;
use App\Filament\Resources\Cranes\Pages\ViewCrane;
use App\Filament\Resources\Cranes\RelationManagers\MaintenanceRecordsRelationManager;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use App\Models\Site;
use App\Models\User;
use App\Services\CraneAttention;
use App\Services\ServiceRecorder;
use App\Services\ServiceSchedule;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceAndAttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_typed_reading_is_recorded_one_cycle_back_as_the_service_meter(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 9000, 'service_interval_km' => 5000, 'last_service_km' => 0]);

        $record = app(ServiceRecorder::class)->record($crane, Carbon::parse('2026-11-12'), 10000, $user);

        $crane->refresh();
        $this->assertSame(5000, $record->km);
        $this->assertSame(10000, $crane->current_km);
        $this->assertSame(10000, app(ServiceSchedule::class)->for($crane)['next_km']);
    }

    public function test_a_service_reading_below_the_current_odometer_is_refused(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 9000, 'service_interval_km' => 10000, 'last_service_km' => 0]);

        $this->expectException(ValidationException::class);

        app(ServiceRecorder::class)->record($crane, Carbon::parse('2026-11-12'), 8000, $user);
    }

    public function test_the_service_is_not_saved_when_the_reading_is_refused(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 9000, 'service_interval_km' => 10000, 'last_service_km' => 0]);

        try {
            app(ServiceRecorder::class)->record($crane, Carbon::parse('2026-11-12'), 8000, $user);
        } catch (ValidationException) {
        }

        $this->assertSame(0, MaintenanceRecord::query()->count());
    }

    public function test_cranes_needing_service_come_before_the_rest(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $fine = $this->crane(['code' => 'A-100', 'current_km' => 100, 'service_interval_km' => 10000, 'last_service_km' => 0]);
        $late = $this->crane(['code' => 'Z-900', 'current_km' => 5000, 'service_interval_km' => 1000, 'last_service_km' => 0]);

        $ids = app(CraneAttention::class)->sortedIds($user);

        $this->assertSame([$late->getKey(), $fine->getKey()], $ids);
    }

    public function test_the_all_cranes_report_downloads_as_pdf(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $this->crane(['current_km' => 100, 'service_interval_km' => 10000, 'last_service_km' => 0]);

        $this->actingAs($user)
            ->get(route('cranes.all-report'))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_the_crane_view_and_edit_pages_render(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 100, 'service_interval_km' => 10000, 'last_service_km' => 0]);

        $this->actingAs($user)->get(route('filament.admin.resources.cranes.view', $crane))->assertOk();
        $this->actingAs($user)->get(route('filament.admin.resources.cranes.index'))->assertOk();
    }

    public function test_a_maintenance_reading_below_the_odometer_is_refused(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 1000, 'service_interval_km' => 5000, 'last_service_km' => 0]);

        Livewire::actingAs($user)
            ->test(MaintenanceRecordsRelationManager::class, [
                'ownerRecord' => $crane,
                'pageClass' => ViewCrane::class,
            ])
            ->callTableAction('create', data: [
                'type' => MaintenanceType::Periodic->value,
                'performed_on' => now()->toDateString(),
                'km' => 500,
                'description' => 'تجربة',
            ])
            ->assertHasActionErrors(['km']);

        $this->assertSame(0, MaintenanceRecord::query()->count());
    }

    public function test_the_roles_page_renders_for_admins(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('filament.admin.resources.roles.index'))->assertOk();
    }

    /** @param array<string, mixed> $overrides */
    private function crane(array $overrides = []): Crane
    {
        return Crane::factory()->create($overrides + [
            'site_id' => Site::factory()->create()->getKey(),
        ]);
    }
}
