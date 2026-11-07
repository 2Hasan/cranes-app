<?php

namespace Tests\Feature;

use App\Enums\FaultSeverity;
use App\Enums\FaultStatus;
use App\Models\Crane;
use App\Models\Fault;
use App\Models\Site;
use App\Models\User;
use App\Notifications\MaintenanceDigestNotification;
use App\Notifications\SevereFaultNotification;
use App\Services\MaintenanceAlerts;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class MaintenanceAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Site $siteA;

    private Site $siteB;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-08 07:00:00');
        $this->seed(RolePermissionSeeder::class);

        $this->siteA = Site::factory()->create();
        $this->siteB = Site::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function userWithRole(string $role, Site $site): User
    {
        $user = User::factory()->create(['site_id' => $site->getKey()]);
        $user->assignRole($role);

        return $user;
    }

    private function overdueCrane(Site $site): Crane
    {
        return Crane::factory()->create([
            'site_id' => $site->getKey(),
            'service_interval_days' => 30,
            'service_interval_km' => null,
            'last_service_date' => '2026-01-01',
        ]);
    }

    public function test_the_digest_goes_only_to_users_who_can_see_the_overdue_crane(): void
    {
        Notification::fake();

        $this->overdueCrane($this->siteA);

        $technicianA = $this->userWithRole('technician', $this->siteA);
        $technicianB = $this->userWithRole('technician', $this->siteB);

        $notified = app(MaintenanceAlerts::class)->sendDailyDigest();

        $this->assertSame(1, $notified);
        Notification::assertSentTo($technicianA, MaintenanceDigestNotification::class);
        Notification::assertNotSentTo($technicianB, MaintenanceDigestNotification::class);
    }

    public function test_nobody_is_notified_when_every_crane_is_on_schedule(): void
    {
        Notification::fake();

        Crane::factory()->create([
            'site_id' => $this->siteA->getKey(),
            'service_interval_days' => 90,
            'last_service_date' => '2026-11-01',
        ]);

        $this->userWithRole('admin', $this->siteA);

        $this->assertSame(0, app(MaintenanceAlerts::class)->sendDailyDigest());
        Notification::assertNothingSent();
    }

    public function test_a_disabled_user_is_not_notified(): void
    {
        Notification::fake();

        $this->overdueCrane($this->siteA);

        $disabled = $this->userWithRole('admin', $this->siteA);
        $disabled->update(['is_active' => false]);

        app(MaintenanceAlerts::class)->sendDailyDigest();

        Notification::assertNotSentTo($disabled, MaintenanceDigestNotification::class);
    }

    public function test_a_severe_fault_alerts_the_site_but_not_the_reporter(): void
    {
        Notification::fake();

        $crane = Crane::factory()->create(['site_id' => $this->siteA->getKey()]);

        $reporter = $this->userWithRole('technician', $this->siteA);
        $colleague = $this->userWithRole('technician', $this->siteA);
        $otherSite = $this->userWithRole('technician', $this->siteB);
        $admin = $this->userWithRole('admin', $this->siteB);

        $fault = Fault::factory()->create([
            'crane_id' => $crane->getKey(),
            'reported_by' => $reporter->getKey(),
            'severity' => FaultSeverity::Critical,
            'status' => FaultStatus::Open,
        ]);

        app(MaintenanceAlerts::class)->notifySevereFault($fault);

        Notification::assertSentTo($colleague, SevereFaultNotification::class);
        Notification::assertSentTo($admin, SevereFaultNotification::class);
        Notification::assertNotSentTo($reporter, SevereFaultNotification::class);
        Notification::assertNotSentTo($otherSite, SevereFaultNotification::class);
    }

    public function test_the_daily_digest_command_runs(): void
    {
        Notification::fake();

        $this->overdueCrane($this->siteA);
        $this->userWithRole('admin', $this->siteA);

        $this->artisan('alerts:maintenance-digest')
            ->expectsOutput('Digest sent to 1 user(s).')
            ->assertSuccessful();
    }
}
