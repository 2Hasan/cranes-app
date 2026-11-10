<?php

namespace Tests\Feature;

use App\Enums\MaintenanceType;
use App\Enums\RepairResult;
use App\Models\Crane;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepairResultTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_repair_record_stores_whether_the_fault_was_fixed(): void
    {
        $crane = Crane::factory()->create();

        $record = MaintenanceRecord::factory()->create([
            'crane_id' => $crane->getKey(),
            'type' => MaintenanceType::Repair,
            'repair_result' => RepairResult::NotFixed,
        ]);

        $this->assertSame(RepairResult::NotFixed, $record->fresh()->repair_result);
    }
}
