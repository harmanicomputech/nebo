<?php

namespace App\Notifications;

use App\Models\LogisticsTrip;
use App\Support\Format;

class TripAssigned extends NeboNotification
{
    public function __construct(public LogisticsTrip $trip) {}

    public function title(): string
    {
        return 'You are on a trip';
    }

    public function body(): string
    {
        return "{$this->trip->reference}: {$this->trip->origin} → {$this->trip->destination}, leaving ".Format::datetime($this->trip->departs_at, 'D j M, g:ia').'.';
    }

    public function url(): ?string
    {
        return route('app.logistics.trips.show', $this->trip);
    }

    public function icon(): string
    {
        return 'truck';
    }
}
