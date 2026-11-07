<?php

namespace Tests\Feature;

use App\Models\Crane;
use App\Models\Site;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CraneReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $crane = Crane::factory()->create();

        // auth middleware sends guests to /login, which forwards them to the Filament login page.
        $this->get(route('cranes.report', $crane))->assertRedirect(route('login'));
    }

    public function test_a_viewer_can_download_a_report_for_a_crane_on_their_site(): void
    {
        $site = Site::factory()->create();
        $crane = Crane::factory()->create(['site_id' => $site->getKey()]);

        $viewer = User::factory()->create(['site_id' => $site->getKey()]);
        $viewer->assignRole('viewer');

        $response = $this->actingAs($viewer)->get(route('cranes.report', $crane));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_a_viewer_cannot_download_a_report_for_another_site(): void
    {
        $crane = Crane::factory()->create(['site_id' => Site::factory()->create()->getKey()]);

        $viewer = User::factory()->create(['site_id' => Site::factory()->create()->getKey()]);
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get(route('cranes.report', $crane))->assertNotFound();
    }

    public function test_a_technician_without_report_permission_is_forbidden(): void
    {
        $site = Site::factory()->create();
        $crane = Crane::factory()->create(['site_id' => $site->getKey()]);

        $technician = User::factory()->create(['site_id' => $site->getKey()]);
        $technician->assignRole('technician');

        $this->actingAs($technician)->get(route('cranes.report', $crane))->assertForbidden();
    }
}
