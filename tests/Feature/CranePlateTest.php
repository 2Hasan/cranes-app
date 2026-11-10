<?php

namespace Tests\Feature;

use App\Models\Crane;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CranePlateTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_plate_shows_only_the_crane_number_on_the_template(): void
    {
        $crane = Crane::factory()->create(['code' => 'R-42']);

        $response = $this->actingAs(User::factory()->create())
            ->get(route('cranes.plate', ['code' => $crane->code]));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/svg+xml');
        $this->assertStringContainsString('<path fill="#000000"', $response->getContent());
        $this->assertStringNotContainsString('4778', $response->getContent());
        $this->assertStringNotContainsString('>R-42</tspan>', $response->getContent());
    }

    public function test_guests_cannot_view_the_plate(): void
    {
        $this->get(route('cranes.plate', ['code' => 'R-42']))->assertRedirect();
    }
}
