<?php

namespace App\Notifications;

use App\Models\EventRequest;

class RequestAssigned extends NeboNotification
{
    public function __construct(public EventRequest $request, public string $by) {}

    public function title(): string
    {
        return 'Request assigned to you';
    }

    public function body(): string
    {
        return "{$this->by} assigned {$this->request->reference} ({$this->request->event_name}) to you.";
    }

    public function url(): ?string
    {
        return route('app.requests.show', $this->request);
    }

    public function icon(): string
    {
        return 'inbox';
    }
}
