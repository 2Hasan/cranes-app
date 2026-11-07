<?php

namespace Tests\Feature;

use App\Enums\MaintenanceType;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use App\Services\ServiceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ServiceScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-07 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_it_is_late_when_the_due_date_has_passed(): void
    {
        $crane = Crane::factory()->create([
            'service_interval_days' => 90,
            'service_interval_km' => null,
            'last_service_date' => '2026-07-01',
        ]);

        $summary = app(ServiceSchedule::class)->for($crane);

        $this->assertSame(ServiceSchedule::STATE_LATE, $summary['state']);
        $this->assertSame('2026-09-29', $summary['next_date']);
        $this->assertLessThan(0, $summary['days_left']);
    }

    public function test_it_is_soon_when_the_due_date_is_within_two_weeks(): void
    {
        $crane = Crane::factory()->create([
            'service_interval_days' => 90,
            'service_interval_km' => null,
            'last_service_date' => '2026-08-20',
        ]);

        $summary = app(ServiceSchedule::class)->for($crane);

        $this->assertSame(ServiceSchedule::STATE_SOON, $summary['state']);
        $this->assertSame('2026-11-18', $summary['next_date']);
        $this->assertSame(11, $summary['days_left']);
    }

    public function test_it_is_late_when_the_km_limit_is_exceeded(): void
    {
        $crane = Crane::factory()->create([
            'service_interval_days' => null,
            'service_interval_km' => 5000,
            'last_service_km' => 10000,
            'current_km' => 15500,
        ]);

        $summary = app(ServiceSchedule::class)->for($crane);

        $this->assertSame(ServiceSchedule::STATE_LATE, $summary['state']);
        $this->assertSame(15000, $summary['next_km']);
        $this->assertSame(-500, $summary['km_left']);
    }

    public function test_the_latest_periodic_record_replaces_the_initial_baseline(): void
    {
        $crane = Crane::factory()->create([
            'service_interval_days' => null,
            'service_interval_km' => 5000,
            'last_service_km' => 10000,
            'current_km' => 21000,
        ]);

        MaintenanceRecord::factory()->create([
            'crane_id' => $crane->getKey(),
            'type' => MaintenanceType::Periodic,
            'performed_on' => '2026-11-01',
            'km' => 20000,
        ]);

        $summary = app(ServiceSchedule::class)->for($crane);

        $this->assertSame(25000, $summary['next_km']);
        $this->assertSame(4000, $summary['km_left']);
        $this->assertSame(ServiceSchedule::STATE_OK, $summary['state']);
    }

    public function test_repair_records_do_not_reset_the_service_schedule(): void
    {
        $crane = Crane::factory()->create([
            'service_interval_days' => null,
            'service_interval_km' => 5000,
            'last_service_km' => 10000,
            'current_km' => 12000,
        ]);

        MaintenanceRecord::factory()->repair()->create([
            'crane_id' => $crane->getKey(),
            'performed_on' => '2026-11-06',
            'km' => 11900,
        ]);

        $summary = app(ServiceSchedule::class)->for($crane);

        $this->assertSame(15000, $summary['next_km']);
    }
}
