<?php

namespace Tests\Feature;

use App\Models\Crane;
use App\Models\Site;
use App\Models\User;
use App\Services\CraneAttention;
use App\Services\ServiceRecorder;
use App\Services\ServiceSchedule;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceAndAttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_service_record_moves_the_next_due_kilometres_forward(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 9000, 'service_interval_km' => 10000, 'last_service_km' => 0]);

        app(ServiceRecorder::class)->record($crane, Carbon::parse('2026-11-12'), 10000, $user);

        $crane->refresh();
        $this->assertSame(10000, $crane->current_km);
        $this->assertSame(20000, app(ServiceSchedule::class)->for($crane)['next_km']);
    }

    public function test_a_service_reading_below_the_current_odometer_keeps_the_odometer(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole('admin');
        $crane = $this->crane(['current_km' => 9000, 'service_interval_km' => 10000, 'last_service_km' => 0]);

        app(ServiceRecorder::class)->record($crane, Carbon::parse('2026-11-12'), 8000, $user);

        $this->assertSame(9000, $crane->fresh()->current_km);
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
        $this->actingAs($user)->get(route('filament.admin.resources.cranes.edit', $crane))->assertOk();
        $this->actingAs($user)->get(route('filament.admin.resources.cranes.index'))->assertOk();
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
