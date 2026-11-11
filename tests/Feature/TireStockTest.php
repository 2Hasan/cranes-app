<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\TireOwner;
use App\Models\User;
use App\Services\TireStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TireStockTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_transfer_moves_tires_out_of_the_source_site(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 1500, Carbon::parse('2026-11-01'), null, $user);

        $transfer = $stock->transfer($traffic, $fursiya, $owner, 100, 'أحمد', Carbon::parse('2026-11-10 09:30'), null, $user);

        $this->assertSame(1400, $stock->balance($traffic, $owner));
        $this->assertSame(100, $stock->balance($fursiya, $owner));
        $this->assertSame(100, $stock->outstanding($transfer));
        $this->assertSame(TireStock::STATUS_PENDING, $stock->status($transfer));
    }

    public function test_good_returns_go_back_and_damaged_tires_leave_the_stock(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 1500, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, $owner, 100, 'أحمد', Carbon::parse('2026-11-10 09:30'), null, $user);

        $stock->recordReturn($transfer, 60, 10, 'سالم', Carbon::parse('2026-11-11 14:00'), $user);

        $this->assertSame(1460, $stock->balance($traffic, $owner));
        $this->assertSame(30, $stock->balance($fursiya, $owner));
        $this->assertSame(30, $stock->outstanding($transfer));
        $this->assertSame(TireStock::STATUS_PARTIAL, $stock->status($transfer));
    }

    public function test_the_total_drops_by_the_damaged_tires_once_everything_is_back(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 1500, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, $owner, 100, 'أحمد', Carbon::parse('2026-11-10 09:30'), null, $user);
        $stock->recordReturn($transfer, 60, 10, 'سالم', Carbon::parse('2026-11-11 14:00'), $user);

        $stock->recordReturn($transfer, 30, 0, 'سالم', Carbon::parse('2026-11-12 08:00'), $user);

        $this->assertSame(0, $stock->balance($fursiya, $owner));
        $this->assertSame(1490, $stock->balance($traffic, $owner));
        $this->assertSame(TireStock::STATUS_RETURNED, $stock->status($transfer));
    }

    public function test_balances_are_kept_apart_per_owner(): void
    {
        [$traffic, $fursiya, $ours, $user, $stock] = $this->makeSitesAndStock();
        $publicWorks = TireOwner::query()->create(['name' => 'الأشغال']);
        $stock->addIntake($traffic, $ours, 1500, Carbon::parse('2026-11-01'), null, $user);
        $stock->addIntake($traffic, $publicWorks, 500, Carbon::parse('2026-11-01'), null, $user);

        $stock->transfer($traffic, $fursiya, $publicWorks, 100, 'أحمد', now(), null, $user);

        $this->assertSame(1500, $stock->balance($traffic, $ours));
        $this->assertSame(400, $stock->balance($traffic, $publicWorks));
        $this->assertSame(100, $stock->balance($fursiya, $publicWorks));
        $this->assertSame(0, $stock->balance($fursiya, $ours));
    }

    public function test_a_site_cannot_send_more_than_the_owner_holds_there(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 50, Carbon::parse('2026-11-01'), null, $user);

        $this->expectException(ValidationException::class);

        $stock->transfer($traffic, $fursiya, $owner, 51, 'أحمد', now(), null, $user);
    }

    public function test_tires_can_be_given_back_to_their_owner_from_the_site_holding_them(): void
    {
        [$traffic, , $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 500, Carbon::parse('2026-11-01'), null, $user);

        $stock->returnToOwner($traffic, $owner, 300, now(), null, $user);

        $this->assertSame(200, $stock->balance($traffic, $owner));
    }

    public function test_the_owner_cannot_receive_more_than_is_held_at_that_site(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 500, Carbon::parse('2026-11-01'), null, $user);
        $stock->transfer($traffic, $fursiya, $owner, 100, 'أحمد', now(), null, $user);

        $this->expectException(ValidationException::class);

        $stock->returnToOwner($fursiya, $owner, 150, now(), null, $user);
    }

    public function test_a_return_cannot_exceed_what_is_still_out(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 100, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, $owner, 20, 'أحمد', now(), null, $user);

        $this->expectException(ValidationException::class);

        $stock->recordReturn($transfer, 25, 0, 'سالم', now(), $user);
    }

    public function test_a_transfer_cannot_go_to_the_same_site(): void
    {
        [$traffic, , $owner, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, $owner, 100, Carbon::parse('2026-11-01'), null, $user);

        $this->expectException(ValidationException::class);

        $stock->transfer($traffic, $traffic, $owner, 10, 'أحمد', now(), null, $user);
    }

    /**
     * @return array{0: Site, 1: Site, 2: TireOwner, 3: User, 4: TireStock}
     */
    private function makeSitesAndStock(): array
    {
        $traffic = Site::factory()->create(['name' => 'المرور']);
        $fursiya = Site::factory()->create(['name' => 'الفروسية']);
        $owner = TireOwner::query()->firstOrCreate(['name' => 'ملكنا']);
        $user = User::factory()->create();

        return [$traffic, $fursiya, $owner, $user, app(TireStock::class)];
    }
}
