<?php

namespace Tests\Feature;

use App\Notifications\WelcomeToNebo;
use App\Support\NotificationChannels;
use App\Support\Settings;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    public function test_notifications_are_stored_in_app_and_shown_in_the_bell(): void
    {
        $user = $this->userWithRole('Crew');
        $user->notify(new WelcomeToNebo);

        $this->actingAs($user)->get('/app')->assertSee('Welcome to Nebo Stage Operations')->assertSee('1 unread');
        $this->actingAs($user)->get('/app/notifications?filter=unread')->assertSee('Welcome to Nebo Stage Operations');
    }

    public function test_opening_a_notification_marks_it_read_and_follows_its_link(): void
    {
        $user = $this->userWithRole('Crew');
        $user->notify(new WelcomeToNebo);
        $id = $user->notifications()->first()->id;

        $this->actingAs($user)->get("/app/notifications/{$id}")->assertRedirect(route('app.profile.edit'));
        $this->assertNotNull($user->notifications()->first()->read_at);
    }

    public function test_users_cannot_open_each_others_notifications(): void
    {
        $owner = $this->userWithRole('Crew');
        $owner->notify(new WelcomeToNebo);
        $id = $owner->notifications()->first()->id;

        $this->actingAs($this->userWithRole('Crew'))->get("/app/notifications/{$id}")->assertNotFound();
        $this->assertNull($owner->notifications()->first()->read_at);
    }

    public function test_mark_all_read(): void
    {
        $user = $this->userWithRole('Crew');
        $user->notify(new WelcomeToNebo);
        $user->notify(new WelcomeToNebo);

        $this->actingAs($user)->post('/app/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_channels_default_to_in_app_and_extend_by_setting(): void
    {
        $user = $this->userWithRole('Crew');

        $this->assertSame(['database', 'webpush'], NotificationChannels::for($user, WelcomeToNebo::class));

        Settings::set('notifications.channels', [WelcomeToNebo::class => ['mail', 'carrier-pigeon']]);
        $this->assertSame(['database', 'webpush', 'mail'], NotificationChannels::for($user, WelcomeToNebo::class));
    }
}
