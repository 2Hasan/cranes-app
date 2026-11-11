<?php

namespace Tests\Feature;

use App\Filament\Resources\Tires\Pages\ListTireTransfers;
use App\Models\Site;
use App\Models\TireOwner;
use App\Models\TireTransfer;
use App\Models\User;
use App\Services\TireStock;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

class TireTransferPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_transfer_and_record_returns_from_the_tire_page(): void
    {
        $this->seed(RolePermissionSeeder::class);

        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $traffic = Site::factory()->create(['name' => 'المرور']);
        $fursiya = Site::factory()->create(['name' => 'الفروسية']);
        $owner = TireOwner::query()->firstOrCreate(['name' => 'ملكنا']);
        app(TireStock::class)->addIntake($traffic, $owner, 1500, Carbon::parse('2026-11-01'), null, $admin);

        Livewire::actingAs($admin)
            ->test(ListTireTransfers::class)
            ->assertSuccessful()
            ->callAction('transfer', [
                'from_site_id' => $traffic->getKey(),
                'tire_owner_id' => $owner->getKey(),
                'to_site_id' => $fursiya->getKey(),
                'quantity' => 100,
                'handled_by' => 'أحمد',
                'moved_at' => '2026-11-10 09:30:00',
            ])
            ->assertHasNoActionErrors();

        $transfer = TireTransfer::query()->firstOrFail();
        $this->assertSame(100, $transfer->quantity);

        Livewire::actingAs($admin)
            ->test(ListTireTransfers::class)
            ->callTableAction('recordReturn', $transfer, [
                'returned_quantity' => 60,
                'damaged_quantity' => 10,
                'returned_by' => 'سالم',
                'returned_at' => '2026-11-11 14:00:00',
            ])
            ->assertHasNoTableActionErrors();

        $this->assertDatabaseHas('tire_returns', [
            'tire_transfer_id' => $transfer->getKey(),
            'returned_quantity' => 60,
            'damaged_quantity' => 10,
            'returned_by' => 'سالم',
        ]);
        $this->assertSame(1460, app(TireStock::class)->balance($traffic, $owner));
    }
}
