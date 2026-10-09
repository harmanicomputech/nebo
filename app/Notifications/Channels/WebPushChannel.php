<?php

namespace App\Notifications\Channels;

use App\Models\PushSubscription;
use App\Models\User;
use App\Notifications\NeboNotification;
use App\Services\Notifications\WebPushSender;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Every notification also pops up on the person's devices (D75). Sending
 * happens after the response, so the action that caused it isn't slowed
 * down; a push failure never breaks that action.
 */
class WebPushChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof NeboNotification
            || ! PushSubscription::where('user_id', $notifiable->id)->exists()) {
            return;
        }

        $payload = [
            'title' => $notification->title(),
            'body' => $notification->body(),
            // Opening marks it read, then follows its link.
            'url' => route('app.notifications.open', $notification->id),
            'tag' => (string) $notification->id,
        ];

        dispatch(function () use ($notifiable, $payload) {
            try {
                app(WebPushSender::class)->send($notifiable, $payload);
            } catch (\Throwable $e) {
                Log::warning('Web push could not be sent', ['exception' => $e]);
            }
        })->afterResponse();
    }
}
