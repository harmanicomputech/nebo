<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\ReturnCheck;

class EquipmentReturnIssues extends NeboNotification
{
    public function __construct(public Event $event, public ReturnCheck $check) {}

    public function title(): string
    {
        return 'Missing or damaged equipment';
    }

    public function body(): string
    {
        return "{$this->event->name}: {$this->check->missing_count} missing, {$this->check->damaged_count} damaged or needing attention after check-in.";
    }

    public function url(): ?string
    {
        return route('app.events.returns', $this->event);
    }

    public function level(): string
    {
        return 'danger';
    }

    public function icon(): string
    {
        return 'triangle-alert';
    }
}
