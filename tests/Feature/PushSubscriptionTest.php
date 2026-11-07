<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PushSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function subscriptionPayload(string $endpoint): array
    {
        return [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM',
                'auth' => 'tBHItJI5svbpez7KI4CCXg',
            ],
        ];
    }

    public function test_a_signed_in_user_can_save_a_push_subscription(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123';

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), $this->subscriptionPayload($endpoint))
            ->assertOk()
            ->assertJson(['subscribed' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'subscribable_id' => $user->getKey(),
            'endpoint' => $endpoint,
        ]);
    }

    public function test_saving_the_same_device_twice_keeps_one_subscription(): void
    {
        $user = User::factory()->create();
        $payload = $this->subscriptionPayload('https://fcm.googleapis.com/fcm/send/same');

        $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();
        $this->actingAs($user)->postJson(route('push.subscribe'), $payload)->assertOk();

        $this->assertSame(1, $user->pushSubscriptions()->count());
    }

    public function test_a_guest_cannot_save_a_subscription(): void
    {
        $this->postJson(route('push.subscribe'), $this->subscriptionPayload('https://example.com/push'))
            ->assertUnauthorized();
    }

    public function test_an_invalid_subscription_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson(route('push.subscribe'), ['endpoint' => 'not-a-url'])
            ->assertUnprocessable();
    }

    public function test_a_user_can_remove_a_subscription(): void
    {
        $user = User::factory()->create();
        $endpoint = 'https://fcm.googleapis.com/fcm/send/remove-me';

        $this->actingAs($user)->postJson(route('push.subscribe'), $this->subscriptionPayload($endpoint))->assertOk();

        $this->actingAs($user)
            ->postJson(route('push.unsubscribe'), ['endpoint' => $endpoint])
            ->assertOk()
            ->assertJson(['subscribed' => false]);

        $this->assertSame(0, $user->pushSubscriptions()->count());
    }
}
