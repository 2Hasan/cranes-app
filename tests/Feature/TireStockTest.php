<?php

namespace Tests\Feature;

use App\Models\Site;
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
        [$traffic, $fursiya, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, 1500, Carbon::parse('2026-11-01'), null, $user);

        $transfer = $stock->transfer($traffic, $fursiya, 100, 'أحمد', Carbon::parse('2026-11-10 09:30'), null, $user);

        $this->assertSame(1400, $stock->balance($traffic));
        $this->assertSame(100, $stock->balance($fursiya));
        $this->assertSame(100, $stock->outstanding($transfer));
        $this->assertSame(TireStock::STATUS_PENDING, $stock->status($transfer));
    }

    public function test_good_returns_go_back_and_damaged_tires_leave_the_stock(): void
    {
        [$traffic, $fursiya, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, 1500, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, 100, 'أحمد', Carbon::parse('2026-11-10 09:30'), null, $user);

        $stock->recordReturn($transfer, 60, 10, 'سالم', Carbon::parse('2026-11-11 14:00'), $user);

        $this->assertSame(1460, $stock->balance($traffic));
        $this->assertSame(30, $stock->balance($fursiya));
        $this->assertSame(30, $stock->outstanding($transfer));
        $this->assertSame(TireStock::STATUS_PARTIAL, $stock->status($transfer));
    }

    public function test_the_total_drops_by_the_damaged_tires_once_everything_is_back(): void
    {
        [$traffic, $fursiya, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, 1500, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, 100, 'أحمد', Carbon::parse('2026-11-10 09:30'), null, $user);
        $stock->recordReturn($transfer, 60, 10, 'سالم', Carbon::parse('2026-11-11 14:00'), $user);

        $stock->recordReturn($transfer, 30, 0, 'سالم', Carbon::parse('2026-11-12 08:00'), $user);

        $this->assertSame(0, $stock->balance($fursiya));
        $this->assertSame(1490, $stock->balance($traffic));
        $this->assertSame(TireStock::STATUS_RETURNED, $stock->status($transfer));
    }

    public function test_a_site_cannot_send_more_than_it_holds(): void
    {
        [$traffic, $fursiya, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, 50, Carbon::parse('2026-11-01'), null, $user);

        $this->expectException(ValidationException::class);

        $stock->transfer($traffic, $fursiya, 51, 'أحمد', now(), null, $user);
    }

    public function test_a_return_cannot_exceed_what_is_still_out(): void
    {
        [$traffic, $fursiya, $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, 100, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, 20, 'أحمد', now(), null, $user);

        $this->expectException(ValidationException::class);

        $stock->recordReturn($transfer, 25, 0, 'سالم', now(), $user);
    }

    public function test_a_transfer_cannot_go_to_the_same_site(): void
    {
        [$traffic, , $user, $stock] = $this->makeSitesAndStock();
        $stock->addIntake($traffic, 100, Carbon::parse('2026-11-01'), null, $user);

        $this->expectException(ValidationException::class);

        $stock->transfer($traffic, $traffic, 10, 'أحمد', now(), null, $user);
    }

    /**
     * @return array{0: Site, 1: Site, 2: User, 3: TireStock}
     */
    private function makeSitesAndStock(): array
    {
        $traffic = Site::factory()->create(['name' => 'المرور']);
        $fursiya = Site::factory()->create(['name' => 'الفروسية']);
        $user = User::factory()->create();

        return [$traffic, $fursiya, $user, app(TireStock::class)];
    }
}
