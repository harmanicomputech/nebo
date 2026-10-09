<?php

namespace App\Services\Notifications;

use App\Models\PushSubscription;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use Minishlink\WebPush\WebPush;

/**
 * Sends pop-up notifications to people's phones and computers through the
 * browsers' push services (D75). The VAPID key pair that identifies this
 * site is created on first use and kept in settings (private key encrypted),
 * so nothing has to be configured on the server.
 */
class WebPushSender
{
    public function publicKey(): string
    {
        return $this->keys()['publicKey'];
    }

    /**
     * Sends to every device the user turned notifications on for; forgets
     * devices the push service reports as gone.
     *
     * @param  array{title: string, body: string, url: string, tag: string, icon?: string}  $payload
     * @return int devices reached
     */
    public function send(User $user, array $payload): int
    {
        $subscriptions = PushSubscription::where('user_id', $user->id)->get();
        if ($subscriptions->isEmpty()) {
            return 0;
        }

        $push = $this->client();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        foreach ($subscriptions as $s) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $s->endpoint,
                'publicKey' => $s->public_key,
                'authToken' => $s->auth_token,
                'contentEncoding' => $s->content_encoding,
            ]), $json, ['TTL' => 86400, 'urgency' => 'high', 'topic' => substr(preg_replace('/[^A-Za-z0-9_-]/', '', $payload['tag']), 0, 32) ?: null]);
        }

        $reached = 0;
        foreach ($push->flush() as $report) {
            $endpoint = $report->getEndpoint();
            if ($report->isSuccess()) {
                $reached++;
                PushSubscription::where('endpoint_hash', PushSubscription::hash($endpoint))->update(['last_used_at' => now()]);
            } elseif ($report->isSubscriptionExpired()) {
                PushSubscription::where('endpoint_hash', PushSubscription::hash($endpoint))->delete();
            } else {
                Log::warning('Web push failed', ['reason' => $report->getReason()]);
            }
        }

        return $reached;
    }

    protected function client(): WebPush
    {
        $keys = $this->keys();
        $subject = str_starts_with((string) config('app.url'), 'https://') ? config('app.url') : 'mailto:'.Settings::string('company.email', 'hello@nebostage.example');

        // With a logger, the library's "install GMP for speed" notice is logged instead of raised as an error (shared hosts often lack GMP).
        $push = new WebPush(auth: ['VAPID' => ['subject' => $subject, 'publicKey' => $keys['publicKey'], 'privateKey' => $keys['privateKey']]], logger: Log::channel());
        $push->setReuseVAPIDHeaders(true);

        return $push;
    }

    /** @return array{publicKey: string, privateKey: string} */
    private function keys(): array
    {
        $public = Settings::get('push.vapid_public');
        $private = Settings::get('push.vapid_private');
        if ($public && $private) {
            return ['publicKey' => $public, 'privateKey' => Crypt::decryptString($private)];
        }

        $keys = VAPID::createVapidKeys();
        Settings::set('push.vapid_public', $keys['publicKey']);
        Settings::set('push.vapid_private', Crypt::encryptString($keys['privateKey']));

        return $keys;
    }
}
