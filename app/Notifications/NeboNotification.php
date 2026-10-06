<?php

namespace App\Notifications;

use App\Support\NotificationChannels;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

/**
 * Base for every in-app notification. Subclasses describe the message; the
 * channels come from NotificationChannels so email / SMS / WhatsApp can be
 * switched on per notification type later without touching each class.
 *
 * Stored data shape (read by the bell and the notifications page):
 *   title, body, url (nullable), level (info|success|warning|danger), icon
 */
abstract class NeboNotification extends Notification
{
    use Queueable;

    abstract public function title(): string;

    abstract public function body(): string;

    public function url(): ?string
    {
        return null;
    }

    /** info | success | warning | danger */
    public function level(): string
    {
        return 'info';
    }

    public function icon(): string
    {
        return 'bell';
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return NotificationChannels::for($notifiable, static::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'body' => $this->body(),
            'url' => $this->url(),
            'level' => $this->level(),
            'icon' => $this->icon(),
        ];
    }
}
