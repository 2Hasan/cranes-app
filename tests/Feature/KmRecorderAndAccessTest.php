<?php

namespace Tests\Feature;

use App\Models\Crane;
use App\Models\KmReading;
use App\Models\Site;
use App\Models\User;
use App\Services\KmRecorder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class KmRecorderAndAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_a_reading_updates_the_crane_and_is_logged(): void
    {
        $crane = Crane::factory()->create(['current_km' => 1000]);
        $recorder = User::factory()->create();

        app(KmRecorder::class)->record($crane, 1500, $recorder);

        $this->assertSame(1500, $crane->fresh()->current_km);
        $this->assertDatabaseHas('km_readings', [
            'crane_id' => $crane->getKey(),
            'recorded_by' => $recorder->getKey(),
            'km' => 1500,
        ]);
    }

    public function test_a_reading_below_the_last_one_is_rejected(): void
    {
        $crane = Crane::factory()->create(['current_km' => 2000]);

        $this->expectException(ValidationException::class);

        app(KmRecorder::class)->record($crane, 1500, User::factory()->create());
    }

    public function test_a_reading_is_not_stored_when_it_is_rejected(): void
    {
        $crane = Crane::factory()->create(['current_km' => 2000]);

        try {
            app(KmRecorder::class)->record($crane, 1500, User::factory()->create());
        } catch (ValidationException) {
            // expected
        }

        $this->assertSame(0, KmReading::query()->count());
        $this->assertSame(2000, $crane->fresh()->current_km);
    }

    public function test_a_technician_only_sees_cranes_from_their_own_site(): void
    {
        $ownSite = Site::factory()->create();
        $otherSite = Site::factory()->create();

        Crane::factory()->create(['site_id' => $ownSite->getKey()]);
        Crane::factory()->create(['site_id' => $otherSite->getKey()]);

        $technician = User::factory()->create(['site_id' => $ownSite->getKey()]);
        $technician->assignRole('technician');

        $this->assertSame(1, Crane::query()->visibleTo($technician)->count());
    }

    public function test_an_admin_sees_cranes_from_every_site(): void
    {
        Crane::factory()->count(2)->create();

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->assertSame(2, Crane::query()->visibleTo($admin)->count());
    }
}
