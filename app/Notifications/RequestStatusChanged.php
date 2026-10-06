<?php

namespace App\Notifications;

use App\Enums\RequestStatus;
use App\Models\EventRequest;

class RequestStatusChanged extends NeboNotification
{
    public function __construct(public EventRequest $request, public RequestStatus $from, public RequestStatus $to, public string $by) {}

    public function title(): string
    {
        return "{$this->request->reference} is now {$this->to->label()}";
    }

    public function body(): string
    {
        return "{$this->by} moved {$this->request->event_name} from {$this->from->label()} to {$this->to->label()}.";
    }

    public function url(): ?string
    {
        return route('app.requests.show', $this->request);
    }

    public function level(): string
    {
        return $this->to->isWon() ? 'success' : 'info';
    }

    public function icon(): string
    {
        return 'history';
    }
}
