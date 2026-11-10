<?php

namespace Tests\Feature;

use App\Models\Crane;
use App\Models\Site;
use App\Services\ServiceSchedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CraneLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deleted_crane_frees_its_code_for_a_new_crane(): void
    {
        $site = Site::factory()->create();
        $old = Crane::factory()->create(['code' => 'R-9', 'site_id' => $site->getKey()]);

        $old->delete();

        $new = Crane::factory()->create(['code' => 'R-9', 'site_id' => $site->getKey()]);

        $this->assertSame('R-9', $new->code);
        $this->assertSoftDeleted('cranes', ['id' => $old->getKey()]);
        $this->assertNotSame('R-9', Crane::withTrashed()->findOrFail($old->getKey())->code);
    }

    public function test_the_summary_shows_remaining_days_and_kilometres(): void
    {
        $site = Site::factory()->create();
        $crane = Crane::factory()->create([
            'site_id' => $site->getKey(),
            'current_km' => 7082,
            'last_service_date' => now()->subDays(100)->toDateString(),
            'service_interval_days' => 90,
            'last_service_km' => 1000,
            'service_interval_km' => 5000,
        ]);

        $schedule = app(ServiceSchedule::class)->for($crane);

        $this->assertSame(ServiceSchedule::STATE_LATE, $schedule['state']);
        $this->assertSame('متأخرة 10 أيام · تجاوزت 1,082 كم', app(ServiceSchedule::class)->summary($schedule));
    }

    public function test_the_summary_is_a_plain_message_without_a_service_cycle(): void
    {
        $crane = Crane::factory()->create(['service_interval_days' => null, 'service_interval_km' => null]);

        $this->assertSame('لم تُحدد دورة الصيانة', app(ServiceSchedule::class)->summary(app(ServiceSchedule::class)->for($crane)));
    }
}
