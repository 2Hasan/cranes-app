<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\InvitationLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class InvitationLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_invitation_link_has_a_valid_signature(): void
    {
        $user = User::factory()->create();

        $link = app(InvitationLink::class)->for($user);

        $this->assertStringContainsString('signature=', $link);
        $this->assertTrue(URL::hasValidSignature(Request::create($link)));
    }
}
