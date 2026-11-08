<?php

namespace Tests\Feature;

use App\Enums\CraneStatus;
use App\Filament\Widgets\CraneStatsOverview;
use App\Models\Crane;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CraneStatsOverviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_overview_shows_counts_for_visible_cranes(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $site = Site::factory()->create();
        Crane::factory()->create(['site_id' => $site->getKey(), 'status' => CraneStatus::UnderMaintenance]);
        Crane::factory()->create(['site_id' => $site->getKey(), 'status' => CraneStatus::Working]);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        Livewire::actingAs($admin)
            ->test(CraneStatsOverview::class)
            ->assertSuccessful()
            ->assertSee('إجمالي الرافعات')
            ->assertSee('تحت الصيانة');
    }

    public function test_viewers_without_crane_permission_do_not_see_the_overview(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create();

        $this->assertFalse(CraneStatsOverview::canView());
        $this->actingAs($user);
        $this->assertFalse(CraneStatsOverview::canView());
    }
}
