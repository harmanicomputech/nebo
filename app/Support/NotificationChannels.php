<?php

namespace App\Support;

/**
 * Decides which channels a notification goes out on. Every notification is
 * in-app ('database') and pops up on the person's devices ('webpush', D75;
 * it does nothing for people who haven't turned it on). Email, SMS and WhatsApp are added by
 * listing a notification class under the channel in the 'notifications.channels'
 * setting (a map of class => list of channels), so delivery rules change
 * without code changes once those channels are configured.
 */
class NotificationChannels
{
    /**
     * @return list<string>
     */
    public static function for(object $notifiable, string $notification): array
    {
        $configured = (array) Settings::get('notifications.channels', []);
        $extra = array_values(array_intersect((array) ($configured[$notification] ?? []), self::available()));

        return array_values(array_unique(array_merge(['database', 'webpush'], $extra)));
    }

    /**
     * Channels that can be switched on. SMS / WhatsApp join this list when
     * their drivers are installed.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        return ['database', 'webpush', 'mail'];
    }
}
