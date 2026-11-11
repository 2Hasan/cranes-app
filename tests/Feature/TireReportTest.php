<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\User;
use App\Services\TireStock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TireReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_with_tire_access_can_download_the_tire_report(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $traffic = Site::factory()->create(['name' => 'المرور']);
        $stock = app(TireStock::class);
        $owner = $stock->ownerByName('ملكنا');
        $stock->addIntake($traffic, $owner, 1000, Carbon::parse('2026-11-01'), null, $admin);
        $stock->recordDamage($traffic, $owner, 100, Carbon::parse('2026-11-02'), 'كسر', $admin);

        $response = $this->actingAs($admin)->get(route('tires.report'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_viewers_without_tire_access_cannot_download_the_report(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $viewer = User::factory()->create();
        $viewer->assignRole('viewer');

        $this->actingAs($viewer)->get(route('tires.report'))->assertForbidden();
    }
}
