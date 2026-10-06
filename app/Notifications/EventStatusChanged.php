<?php

namespace App\Notifications;

use App\Enums\EventStatus;
use App\Models\Event;

class EventStatusChanged extends NeboNotification
{
    public function __construct(public Event $event, public EventStatus $from, public EventStatus $to, public string $by) {}

    public function title(): string
    {
        return "{$this->event->name} is now {$this->to->label()}";
    }

    public function body(): string
    {
        return "{$this->by} moved {$this->event->reference} from {$this->from->label()} to {$this->to->label()}.";
    }

    public function url(): ?string
    {
        return route('app.events.show', $this->event);
    }

    public function icon(): string
    {
        return 'calendar-range';
    }
}
