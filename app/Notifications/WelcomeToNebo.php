<?php

namespace App\Notifications;

class WelcomeToNebo extends NeboNotification
{
    public function title(): string
    {
        return 'Welcome to Nebo Stage Operations';
    }

    public function body(): string
    {
        return 'Your account is ready. Change the temporary password from your profile.';
    }

    public function url(): ?string
    {
        return route('app.profile.edit');
    }

    public function level(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'sparkles';
    }
}
