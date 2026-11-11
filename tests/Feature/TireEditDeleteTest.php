<?php

namespace Tests\Feature;

use App\Models\Site;
use App\Models\TireIntake;
use App\Models\TireOwner;
use App\Models\TireReturn;
use App\Models\User;
use App\Services\TireStock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TireEditDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_a_transfer_removes_its_returns_and_restores_the_source(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->setup_();
        $stock->addIntake($traffic, $owner, 1500, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, $owner, 100, 'أحمد', now(), null, $user);
        $stock->recordReturn($transfer, 60, 10, 'سالم', now(), $user);

        $stock->deleteTransfer($transfer);

        $this->assertSame(1500, $stock->balance($traffic, $owner));
        $this->assertSame(0, $stock->balance($fursiya, $owner));
        $this->assertSame(0, TireReturn::query()->count());
    }

    public function test_a_delete_that_would_leave_a_negative_balance_is_rolled_back(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->setup_();
        $intake = $stock->addIntake($traffic, $owner, 100, Carbon::parse('2026-11-01'), null, $user);
        $intakeId = $intake->getKey();
        $stock->transfer($traffic, $fursiya, $owner, 100, 'أحمد', now(), null, $user);

        try {
            $stock->deleteIntake($intake);
            $this->fail('The delete should have been refused.');
        } catch (ValidationException $exception) {
            $this->assertTrue(TireIntake::query()->whereKey($intakeId)->exists());
            $this->assertSame(0, $stock->balance($traffic, $owner));
        }
    }

    public function test_a_transfer_cannot_be_reduced_below_what_came_back(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->setup_();
        $stock->addIntake($traffic, $owner, 100, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, $owner, 50, 'أحمد', now(), null, $user);
        $stock->recordReturn($transfer, 30, 0, 'سالم', now(), $user);

        $this->expectException(ValidationException::class);

        $stock->updateTransfer($transfer, $traffic, $fursiya, $owner, 20, 'أحمد', now(), null);
    }

    public function test_editing_a_transfer_changes_both_balances(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->setup_();
        $stock->addIntake($traffic, $owner, 100, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, $owner, 20, 'أحمد', now(), null, $user);

        $stock->updateTransfer($transfer, $traffic, $fursiya, $owner, 40, 'أحمد', now(), null);

        $this->assertSame(60, $stock->balance($traffic, $owner));
        $this->assertSame(40, $stock->balance($fursiya, $owner));
    }

    public function test_a_return_edit_cannot_exceed_the_transfer_quantity(): void
    {
        [$traffic, $fursiya, $owner, $user, $stock] = $this->setup_();
        $stock->addIntake($traffic, $owner, 100, Carbon::parse('2026-11-01'), null, $user);
        $transfer = $stock->transfer($traffic, $fursiya, $owner, 20, 'أحمد', now(), null, $user);
        $return = $stock->recordReturn($transfer, 10, 0, 'سالم', now(), $user);

        $this->expectException(ValidationException::class);

        $stock->updateReturn($return, 25, 0, 'سالم', now());
    }

    public function test_editing_a_damage_adjusts_the_site_balance(): void
    {
        [$traffic, , $owner, $user, $stock] = $this->setup_();
        $stock->addIntake($traffic, $owner, 1000, Carbon::parse('2026-11-01'), null, $user);
        $damage = $stock->recordDamage($traffic, $owner, 100, now(), null, $user);

        $stock->updateDamage($damage, $traffic, $owner, 150, now(), null);

        $this->assertSame(850, $stock->balance($traffic, $owner));
    }

    /**
     * @return array{0: Site, 1: Site, 2: TireOwner, 3: User, 4: TireStock}
     */
    private function setup_(): array
    {
        $traffic = Site::factory()->create(['name' => 'المرور']);
        $fursiya = Site::factory()->create(['name' => 'الفروسية']);
        $owner = TireOwner::query()->firstOrCreate(['name' => 'ملكنا']);
        $user = User::factory()->create();

        return [$traffic, $fursiya, $owner, $user, app(TireStock::class)];
    }
}
