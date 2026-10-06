<?php

namespace App\Notifications;

use App\Models\Event;
use App\Support\Format;

class AssignedToEvent extends NeboNotification
{
    public function __construct(public Event $event, public string $role) {}

    public function title(): string
    {
        return "You're on {$this->event->name}";
    }

    public function body(): string
    {
        return "Role: {$this->role}. Setup ".Format::datetime($this->event->setup_starts_at)." at {$this->event->venue}.";
    }

    public function url(): ?string
    {
        return route('app.events.show', $this->event);
    }

    public function level(): string
    {
        return 'success';
    }

    public function icon(): string
    {
        return 'users';
    }
}
