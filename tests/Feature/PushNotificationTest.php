<?php

namespace Tests\Feature;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\WelcomeToNebo;
use App\Services\Notifications\WebPushSender;
use App\Support\Settings;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\SubscriptionInterface;
use Minishlink\WebPush\WebPush;
use Tests\TestCase;

class PushNotificationTest extends TestCase
{
    private function subscribe(User $user, string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc123'): PushSubscription
    {
        return PushSubscription::create(['user_id' => $user->id, 'endpoint' => $endpoint, 'endpoint_hash' => PushSubscription::hash($endpoint),
            'public_key' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u-Ts1XbjhazAkj7I99e8QcYP7DkM', 'auth_token' => 'tBHItJI5svbpez7KI4CCXg']);
    }

    public function test_open_pages_get_new_notifications_and_the_unread_count(): void
    {
        $user = $this->userWithRole('Crew');
        $this->travel(-10)->minutes();
        $user->notify(new WelcomeToNebo);
        $this->travelBack();
        $since = now()->subSeconds(3)->toIso8601String();
        $user->notify(new WelcomeToNebo);
        $this->userWithRole('Crew')->notify(new WelcomeToNebo); // someone else's

        $response = $this->actingAs($user)->getJson(route('app.notifications.poll', ['since' => $since]))->assertOk();

        $response->assertJsonPath('unread', 2)->assertJsonCount(1, 'items');
        $this->assertStringContainsString('/app/notifications/', $response->json('items.0.url'));
        $this->assertNotEmpty($response->json('now'));
    }

    public function test_a_device_turns_pop_ups_on_and_off(): void
    {
        $user = $this->userWithRole('Crew');
        $body = ['endpoint' => 'https://fcm.googleapis.com/fcm/send/xyz', 'keys' => ['p256dh' => 'BNcRdreALRFXTkOOUHK1EtK2wtaz5Ry4YfYCA_0QTpQtUbVlUls0VJXg7A8u', 'auth' => 'tBHItJI5svbpez7KI4CCXg']];

        $this->actingAs($user)->postJson(route('app.push.subscribe'), $body)->assertOk();
        $this->actingAs($user)->postJson(route('app.push.subscribe'), $body)->assertOk(); // same device again: one row
        $this->assertSame(1, $user->pushSubscriptions()->count());

        $this->actingAs($user)->postJson(route('app.push.subscribe'), ['endpoint' => 'http://insecure.test/x'] + $body)->assertUnprocessable();

        $this->actingAs($user)->deleteJson(route('app.push.unsubscribe'), ['endpoint' => $body['endpoint']])->assertOk();
        $this->assertSame(0, $user->pushSubscriptions()->count());
    }

    public function test_every_notification_is_pushed_to_the_persons_devices_after_the_response(): void
    {
        $user = $this->userWithRole('Crew');
        $this->subscribe($user);
        $sent = [];
        $this->app->instance(WebPushSender::class, new class($sent) extends WebPushSender
        {
            public function __construct(private array &$log) {}

            public function send(User $user, array $payload): int
            {
                $this->log[] = [$user->id, $payload];

                return 1;
            }
        });

        $user->notify(new WelcomeToNebo);
        $this->assertSame([], $sent, 'nothing is sent before the response');
        $this->app->terminate();

        $this->assertCount(1, $sent);
        [$to, $payload] = $sent[0];
        $this->assertSame($user->id, $to);
        $this->assertSame((new WelcomeToNebo)->title(), $payload['title']);
        $this->assertStringContainsString('/app/notifications/'.$user->notifications()->value('id'), $payload['url']);
    }

    public function test_the_site_keys_are_created_once_and_gone_devices_are_forgotten(): void
    {
        $user = $this->userWithRole('Crew');
        $live = $this->subscribe($user, 'https://push.example.org/live');
        $gone = $this->subscribe($user, 'https://push.example.org/gone');

        $sender = new class extends WebPushSender
        {
            protected function client(): WebPush
            {
                return new class extends WebPush
                {
                    private array $endpoints = [];

                    public function __construct() {}

                    public function queueNotification(SubscriptionInterface $subscription, ?string $payload = null, array $options = [], array $auth = []): void
                    {
                        $this->endpoints[] = $subscription->getEndpoint();
                    }

                    public function flush(?int $batchSize = null): \Generator
                    {
                        foreach ($this->endpoints as $endpoint) {
                            $ok = ! str_ends_with($endpoint, 'gone');
                            yield new MessageSentReport(new Request('POST', $endpoint), new Response($ok ? 201 : 410), $ok, $ok ? 'OK' : 'Gone');
                        }
                    }
                };
            }
        };

        $this->assertSame(1, $sender->send($user, ['title' => 'Hi', 'body' => 'There', 'url' => 'https://x.test', 'tag' => 'abc']));
        $this->assertNotNull($live->fresh()->last_used_at);
        $this->assertNull($gone->fresh());

        $key = $sender->publicKey();
        $this->assertSame($key, (new WebPushSender)->publicKey(), 'the key pair is kept');
        $this->assertStringNotContainsString('-----', (string) Settings::get('push.vapid_private'));
    }

    public function test_the_push_client_starts_on_servers_without_gmp_or_bcmath(): void
    {
        // The library raises a notice when GMP/BCMath are missing; it must be logged, not thrown.
        $client = (fn () => $this->client())->call(new WebPushSender);
        $this->assertInstanceOf(WebPush::class, $client);
    }

    public function test_pages_carry_what_the_browser_needs(): void
    {
        $this->actingAs($this->superAdmin())->get(route('app.dashboard'))->assertOk()
            ->assertSee('data-poll-url', false)->assertSee('data-push-key="'.app(WebPushSender::class)->publicKey().'"', false)
            ->assertSee('data-unread-badge', false);

        $sw = file_get_contents(public_path('sw.js'));
        $this->assertStringContainsString("addEventListener('push'", $sw);
        $this->assertStringContainsString("addEventListener('notificationclick'", $sw);
    }
}
