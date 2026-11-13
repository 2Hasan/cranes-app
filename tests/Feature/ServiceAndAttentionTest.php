<?php

namespace Tests\Feature;

use App\Enums\MaintenanceType;
use App\Filament\Resources\Cranes\Pages\ViewCrane;
use App\Filament\Resources\Cranes\RelationManagers\MaintenanceRecordsRelationManager;
use App\Models\Crane;
use App\Models\KmReading;
use App\Models\MaintenanceRecord;
use App\Models\Site;
use App\Models\User;
use App\Services\CraneAttention;
use App\Services\KmRecorder;
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

    public function test_a_service_stores_the_next_due_date_and_meter_without_changing_the_odometer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 9000, 'service_interval_km' => 5000, 'service_interval_days' => 30]);

        $record = app(ServiceRecorder::class)->record($crane, Carbon::parse('2026-12-12'), 14000, $user);

        $this->assertSame('2026-12-12', $record->next_service_date->toDateString());
        $this->assertSame(14000, $record->next_service_km);
        $this->assertSame(9000, $crane->fresh()->current_km);

        $schedule = app(ServiceSchedule::class)->for($crane->fresh());
        $this->assertSame('2026-12-12', $schedule['next_date']);
        $this->assertSame(14000, $schedule['next_km']);
    }

    public function test_a_service_meter_already_passed_shows_the_crane_as_late(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 9000, 'service_interval_km' => 5000]);

        app(ServiceRecorder::class)->record($crane, now()->addDays(60), 8000, $user);

        $this->assertSame(ServiceSchedule::STATE_LATE, app(ServiceSchedule::class)->for($crane->fresh())['state']);
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

    public function test_a_mistyped_latest_reading_can_be_corrected_but_not_below_the_one_before(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $crane = $this->crane(['current_km' => 540515]);
        KmReading::create(['crane_id' => $crane->getKey(), 'recorded_by' => $user->getKey(), 'km' => 530000, 'recorded_at' => now()->subDay()]);
        KmReading::create(['crane_id' => $crane->getKey(), 'recorded_by' => $user->getKey(), 'km' => 540515, 'recorded_at' => now()]);

        app(KmRecorder::class)->correctLatest($crane, 536945);

        $this->assertSame(536945, $crane->fresh()->current_km);

        $this->expectException(ValidationException::class);

        app(KmRecorder::class)->correctLatest($crane, 436830);
    }

    public function test_an_admin_can_force_a_lower_correction(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $crane = $this->crane(['current_km' => 540515]);
        KmReading::create(['crane_id' => $crane->getKey(), 'recorded_by' => $user->getKey(), 'km' => 536945, 'recorded_at' => now()->subDay()]);
        KmReading::create(['crane_id' => $crane->getKey(), 'recorded_by' => $user->getKey(), 'km' => 540515, 'recorded_at' => now()]);

        app(KmRecorder::class)->correctLatest($crane, 436830, allowBelowPrevious: true);

        $this->assertSame(436830, $crane->fresh()->current_km);

        $this->expectException(ValidationException::class);

        app(KmRecorder::class)->correctLatest($crane, 520000);
    }

    public function test_the_admin_pages_link_the_web_app_manifest(): void
    {
        $this->get(route('filament.admin.auth.login'))
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('/manifest.webmanifest', false);

        $this->assertFileExists(public_path('manifest.webmanifest'));
        $this->assertFileExists(public_path('icons/icon-192.png'));
        $this->assertFileExists(public_path('icons/icon-512.png'));
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
